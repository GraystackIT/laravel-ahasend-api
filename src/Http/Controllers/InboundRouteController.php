<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Http\Controllers;

use GraystackIT\Ahasend\Data\InboundMessage;
use GraystackIT\Ahasend\Events\InboundMailReceived;
use GraystackIT\Ahasend\Models\AhasendRoute;
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
 *   configured, which is the simple case for an application's own few domains.
 * - `/{path}/{route}` for routes this package provisions per customer domain.
 *   There is no chance to put those secrets in a config file, so the route id
 *   in the URL is what the stored secret is looked up by.
 *
 * Deliberately minimal either way: verify, normalise, fire an event, return 200.
 * Anything slower risks an Ahasend retry and therefore a duplicate.
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

        if (! $inboundRoute instanceof AhasendRoute) {
            Log::warning('Ahasend inbound: unknown route', [
                'route' => $route,
                'ip'    => $request->ip(),
            ]);

            return response()->json(['error' => 'Unknown route'], 404);
        }

        if (! $this->signatureIsValid($request, $inboundRoute->secret)) {
            return $this->rejectSignature($request, ['route' => $route]);
        }

        return $this->accept($request, $inboundRoute);
    }

    /**
     * Handle inbound mail arriving on a route managed in the dashboard.
     *
     * Verified against the configured secrets: an application's own domains are
     * few and known up front, so their secrets belong in the environment rather
     * than in a table.
     */
    public function handleStatic(Request $request): JsonResponse
    {
        /** @var list<string> $secrets */
        $secrets = (array) config('ahasend.inbound.secrets', []);

        if ($secrets === []) {
            Log::warning('Ahasend inbound: no route secret configured, payload rejected', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Inbound not configured'], 503);
        }

        foreach ($secrets as $secret) {
            if ($this->signatureIsValid($request, $secret)) {
                return $this->accept($request);
            }
        }

        return $this->rejectSignature($request, []);
    }

    /**
     * Normalise a verified payload and announce it.
     */
    private function accept(Request $request, ?AhasendRoute $route = null): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        // Standard Webhooks format: { type, timestamp, data: { ... } }
        /** @var array<string, mixed> $data */
        $data    = (array) ($payload['data'] ?? $payload);
        $message = InboundMessage::fromArray($data);

        Log::info('Ahasend inbound: message received', [
            'route'      => $route?->public_id,
            'message_id' => $message->messageId,
            'from'       => $message->from,
            'to'         => $message->to,
        ]);

        InboundMailReceived::dispatch(
            $message,
            $this->webhookDeliveryId($request) ?: $message->messageId,
            $route,
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function rejectSignature(Request $request, array $context): JsonResponse
    {
        Log::warning('Ahasend inbound: invalid signature', [
            ...$context,
            'ip' => $request->ip(),
        ]);

        return response()->json(['error' => 'Invalid signature'], 401);
    }
}
