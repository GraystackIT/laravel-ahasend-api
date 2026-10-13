<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Http\Controllers;

use GraystackIT\Ahasend\Contracts\InboundScreen;
use GraystackIT\Ahasend\Data\InboundDelivery;
use GraystackIT\Ahasend\Data\InboundMessage;
use GraystackIT\Ahasend\Events\InboundMailReceived;
use GraystackIT\Ahasend\Jobs\ProcessInboundDelivery;
use GraystackIT\Ahasend\Models\AhasendRoute;
use GraystackIT\Ahasend\Support\InboundChannel;
use GraystackIT\Ahasend\Support\InboundChannels;
use GraystackIT\Ahasend\Support\InboundSecrets;
use GraystackIT\Ahasend\Support\PayloadCapture;
use GraystackIT\Ahasend\Traits\VerifiesWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Receives inbound mail from an Ahasend route.
 *
 * Separate from the event webhook because a route signs with its own secret,
 * not the account-wide webhook secret.
 *
 * Three ways in:
 *
 * - `/{static_path}/{channel}` for a named channel registered in
 *   {@see InboundChannels}: one endpoint per receiving domain, verified against
 *   that channel's secret only, with its own rate limit and queue.
 * - `/{static_path}` for routes managed in the dashboard that have not moved to
 *   a channel yet. Deprecated: it tries the configured secret of whichever
 *   domain the payload names.
 * - `/{path}/{route}` for routes this package provisions per customer domain.
 *   There is no chance to put those secrets in a config file, so the route id
 *   in the URL is what the stored secret is looked up by.
 *
 * Either way the secret that verifies the payload is what authenticates the
 * delivery domain — see {@see InboundMailReceived::$deliveredForDomain}.
 *
 * Deliberately minimal: verify, store, queue, return 200. With a handoff queue
 * configured nothing else happens inside the request — screening and every
 * listener run in {@see ProcessInboundDelivery}. Without one the screen and the
 * event run inline, as they always did.
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
     * Handle inbound mail arriving on a named channel.
     *
     * Only the channel's own secret is tried. An unknown channel answers like a
     * bad signature, so the response does not tell the two apart.
     */
    public function handleChannel(Request $request, string $channel, InboundChannels $channels): JsonResponse
    {
        $inboundChannel = $channels->get($channel);

        if (! $inboundChannel instanceof InboundChannel) {
            return $this->rejectSignature($request, ['channel' => $channel, 'reason' => 'unknown channel']);
        }

        $secret = $channels->secretFor($inboundChannel);

        // An empty secret would make signatureIsValid() accept anything.
        if ($secret === null) {
            Log::error('Ahasend inbound: channel has no signing secret, refusing delivery', [
                'channel' => $channel,
            ]);

            return response()->json(['error' => 'Channel not configured'], 503);
        }

        if (! $this->signatureIsValid($request, $secret)) {
            return $this->rejectSignature($request, ['channel' => $channel]);
        }

        return $this->accept($request, $inboundChannel->domain, null, null, $inboundChannel);
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
        $secrets = [
            ...(array) config('ahasend.inbound.secrets', []),
            ...app(InboundChannels::class)->secretsByDomain(),
        ];

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
     * Hand a verified payload on.
     *
     * With a handoff queue the raw body goes to the handoff disk and a job is
     * queued — nothing else runs inside the request. Without one the screen
     * and the event run inline.
     */
    private function accept(
        Request $request,
        string $domain,
        ?AhasendRoute $route = null,
        ?InboundMessage $message = null,
        ?InboundChannel $channel = null,
    ): JsonResponse {
        $channel ??= app(InboundChannels::class)->forDomain($domain);
        $deliveryId = $this->webhookDeliveryId($request) ?: (string) $request->json('data.message_id', $request->json('message_id', ''));

        PayloadCapture::write($request, $domain, ['delivered_for_domain' => $domain]);

        Log::debug('Ahasend inbound: message received', [
            'domain'      => $domain,
            'route'       => $route?->public_id,
            'channel'     => $channel?->name,
            'delivery_id' => $deliveryId,
        ]);

        $queue = $channel?->queue ?? trim((string) config('ahasend.inbound.handoff.queue', ''));

        if ($queue !== '') {
            return $this->handOff($request, $queue, $deliveryId, $domain, $route, $channel);
        }

        $message ??= $this->message($request);

        $delivery = new InboundDelivery(
            message: $message,
            deliveryId: $deliveryId !== '' ? $deliveryId : $message->messageId,
            deliveredForDomain: $domain,
            route: $route,
            channel: $channel?->name,
        );

        $verdict = app(InboundScreen::class)->screen($delivery);

        // Inline there is no queue to release the delivery back to, so a
        // deferral asks Ahasend to deliver it again later instead.
        if ($verdict->defers()) {
            return response()
                ->json(['error' => 'Try again later'], 503)
                ->header('Retry-After', (string) $verdict->delaySeconds);
        }

        if ($verdict->accepts()) {
            InboundMailReceived::dispatch($delivery->message, $delivery->deliveryId, $domain, $route);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Store the raw body and queue its processing.
     */
    private function handOff(
        Request $request,
        string $queue,
        string $deliveryId,
        string $domain,
        ?AhasendRoute $route,
        ?InboundChannel $channel,
    ): JsonResponse {
        $disk = (string) config('ahasend.inbound.handoff.disk', 'local');
        $path = 'ahasend-inbound/' . now()->format('Y-m-d') . '/' . Str::uuid()->toString() . '.json';

        Storage::disk($disk)->put($path, $request->getContent());

        $job = new ProcessInboundDelivery(
            disk: $disk,
            path: $path,
            deliveryId: $deliveryId !== '' ? $deliveryId : $path,
            deliveredForDomain: $domain,
            routeId: $route?->getKey(),
            channel: $channel?->name,
        );

        $connection = trim((string) config('ahasend.inbound.handoff.connection', ''));

        dispatch($job->onQueue($queue)->onConnection($connection !== '' ? $connection : null));

        return response()->json(['status' => 'ok']);
    }

    /**
     * The payload as a normalised message.
     */
    private function message(Request $request): InboundMessage
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        return InboundMessage::fromPayload($payload);
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
