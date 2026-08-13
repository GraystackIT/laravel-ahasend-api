<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Http\Controllers;

use GraystackIT\Ahasend\Data\InboundMessage;
use GraystackIT\Ahasend\Events\InboundMailReceived;
use GraystackIT\Ahasend\Models\AhasendRoute;
use GraystackIT\Ahasend\Support\InboundSecrets;
use GraystackIT\Ahasend\Traits\VerifiesWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound mail from an Ahasend route.
 *
 * Separate from the event webhook because a route signs with its own secret,
 * not the account-wide webhook secret.
 *
 * Two ways in, because there are two ways a route comes into existence:
 *
 * - `/{static_path}` for routes managed in the dashboard. Their secrets are
 *   configured per domain, which is the simple case for an application's own
 *   few domains.
 * - `/{path}/{route}` for routes this package provisions per customer domain.
 *   There is no chance to put those secrets in a config file, so the route id
 *   in the URL is what the stored secret is looked up by.
 *
 * Either way the secret that verifies the payload is what authenticates the
 * delivery domain — see {@see InboundMailReceived::$deliveredForDomain}.
 *
 * Deliberately minimal: verify, normalise, fire an event, return 200. Anything
 * slower risks an Ahasend retry and therefore a duplicate.
 */
class InboundRouteController extends Controller
{
    use VerifiesWebhookSignature;

    /**
     * Handle inbound mail arriving on a provisioned route.
     */
    public function handle(Request $request, string $route): JsonResponse
    {
        $inboundRoute = AhasendRoute::query()->where('public_id', $route)->first();

        // Unknown and unverified answer alike, so the ids in our route URLs
        // cannot be enumerated by telling the two responses apart.
        if (! $inboundRoute instanceof AhasendRoute) {
            return $this->rejectSignature($request, ['route' => $route, 'reason' => 'unknown route']);
        }

        // An empty stored secret would make signatureIsValid() accept anything,
        // and this endpoint is public. Refuse instead of waving it through.
        if ((string) $inboundRoute->secret === '') {
            Log::error('Ahasend inbound: route has no signing secret, refusing delivery', [
                'route' => $route,
            ]);

            return response()->json(['error' => 'Route not configured'], 503);
        }

        if (! $this->signatureIsValid($request, $inboundRoute->secret)) {
            return $this->rejectSignature($request, ['route' => $route]);
        }

        $domain = $inboundRoute->domain?->domain
            ?? InboundSecrets::domainOf($inboundRoute->recipient)
            ?? '';

        return $this->accept($request, $domain, $inboundRoute);
    }

    /**
     * Handle inbound mail arriving on a route managed in the dashboard.
     *
     * The configured secret of the delivery domain verifies the payload, and
     * passing that check is what proves which domain the mail was routed for.
     */
    public function handleStatic(Request $request): JsonResponse
    {
        /** @var array<string, string> $secrets */
        $secrets = (array) config('ahasend.inbound.secrets', []);

        if ($secrets === []) {
            Log::error('Ahasend inbound: no route secret configured, refusing delivery', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Inbound not configured'], 503);
        }

        $message = $this->message($request);

        foreach ($this->candidateSecrets($message, $secrets) as [$domain, $secret]) {
            if ($this->signatureIsValid($request, $secret)) {
                return $this->accept($request, $domain, null, $message);
            }
        }

        return $this->rejectSignature($request, []);
    }

    /**
     * Secrets worth trying for this payload, most likely first.
     *
     * Reading the domain from an unverified payload is safe — it only picks the
     * key, the HMAC remains the gate. When no delivery address matches a
     * configured domain (a payload shape we have not seen), every configured
     * secret is tried rather than dropping mail that may well be genuine.
     *
     * @param  array<string, string>  $secrets
     * @return list<array{0: string, 1: string}>  [domain, secret] pairs.
     */
    private function candidateSecrets(InboundMessage $message, array $secrets): array
    {
        $domains = [];

        foreach ($message->deliveryAddresses() as $address) {
            $domain = InboundSecrets::domainOf($address);

            if ($domain !== null) {
                $domains[] = $domain;
            }
        }

        $matched = InboundSecrets::match($domains, $secrets);

        if ($matched !== null) {
            return [$matched];
        }

        return array_map(
            static fn (string $domain, string $secret): array => [$domain, $secret],
            array_keys($secrets),
            array_values($secrets),
        );
    }

    /**
     * Normalise a verified payload and announce it.
     */
    private function accept(
        Request $request,
        string $domain,
        ?AhasendRoute $route = null,
        ?InboundMessage $message = null,
    ): JsonResponse {
        $message ??= $this->message($request);

        Log::debug('Ahasend inbound: message received', [
            'domain'     => $domain,
            'route'      => $route?->public_id,
            'message_id' => $message->messageId,
            'from'       => $message->from,
            'recipient'  => $message->recipient,
        ]);

        InboundMailReceived::dispatch(
            $message,
            $this->webhookDeliveryId($request) ?: $message->messageId,
            $domain,
            $route,
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * The payload as a normalised message.
     */
    private function message(Request $request): InboundMessage
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        // Standard Webhooks format: { type, timestamp, data: { ... } }
        /** @var array<string, mixed> $data */
        $data = (array) ($payload['data'] ?? $payload);

        return InboundMessage::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function rejectSignature(Request $request, array $context): JsonResponse
    {
        Log::warning('Ahasend inbound: rejected', [
            ...$context,
            'ip' => $request->ip(),
        ]);

        return response()->json(['error' => 'Invalid signature'], 401);
    }
}
