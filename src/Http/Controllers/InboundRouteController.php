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
 * Separate from the event webhook because every route signs with its own
 * secret — the route is identified by the opaque id in the path, and its
 * secret is what the payload is verified against.
 *
 * Deliberately minimal: verify, normalise, fire an event, return 200. Anything
 * slower risks an Ahasend retry and therefore a duplicate.
 */
class InboundRouteController extends Controller
{
    use VerifiesWebhookSignature;

    /**
     * Handle an inbound routing request for the given route.
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
            Log::warning('Ahasend inbound: invalid signature', [
                'route' => $route,
                'ip'    => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        // Standard Webhooks format: { type, timestamp, data: { ... } }
        /** @var array<string, mixed> $data */
        $data    = (array) ($payload['data'] ?? $payload);
        $message = InboundMessage::fromArray($data);

        Log::info('Ahasend inbound: message received', [
            'route'      => $route,
            'message_id' => $message->messageId,
            'from'       => $message->from,
            'to'         => $message->to,
        ]);

        InboundMailReceived::dispatch(
            $message,
            $inboundRoute,
            $this->webhookDeliveryId($request) ?: $message->messageId,
        );

        return response()->json(['status' => 'ok']);
    }
}
