<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Http\Controllers;

use GraystackIT\Ahasend\Events\MailBounced;
use GraystackIT\Ahasend\Events\MailDelivered;
use GraystackIT\Ahasend\Events\MailFailed;
use GraystackIT\Ahasend\Events\MailOpened;
use GraystackIT\Ahasend\Models\AhasendMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle an inbound Ahasend webhook event.
     *
     * Ahasend POSTs a JSON payload to this endpoint on each status change.
     * If a webhook secret is configured the signature is validated first.
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('Ahasend webhook: invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $event     = (string) ($payload['event'] ?? '');
        $messageId = (string) ($payload['message_id'] ?? '');
        $recipient = (string) ($payload['recipient'] ?? $payload['email'] ?? '');

        Log::info('Ahasend webhook received', [
            'event'      => $event,
            'message_id' => $messageId,
            'recipient'  => $recipient,
        ]);

        $this->persistStatusUpdate($messageId, $event, $payload);
        $this->dispatchEvent($event, $messageId, $recipient, $payload);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify the Ahasend webhook signature when a secret is configured.
     *
     * Ahasend signs the raw body with HMAC-SHA256 and sends the signature
     * in the `X-Ahasend-Signature` header.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('ahasend.webhook.secret');

        if ($secret === null || $secret === '') {
            return true; // Signature verification disabled.
        }

        $signature = $request->header('X-Ahasend-Signature', '');

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Update the stored message status when using the database driver.
     *
     * @param  array<string, mixed>  $payload
     */
    private function persistStatusUpdate(string $messageId, string $event, array $payload): void
    {
        if (! config('ahasend.store_logs', false)) {
            return;
        }

        if (config('ahasend.storage_driver') !== 'database') {
            Log::info('Ahasend webhook event', [
                'event'      => $event,
                'message_id' => $messageId,
                'payload'    => $payload,
            ]);

            return;
        }

        $status = match ($event) {
            'delivered' => 'delivered',
            'opened'    => 'opened',
            'failed'    => 'failed',
            'bounced'   => 'bounced',
            default     => $event,
        };

        AhasendMessage::where('message_id', $messageId)
            ->update(['status' => $status, 'payload' => $payload]);
    }

    /**
     * Fire the appropriate Laravel event for the webhook event type.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dispatchEvent(string $event, string $messageId, string $recipient, array $payload): void
    {
        match ($event) {
            'delivered' => MailDelivered::dispatch($messageId, $recipient, $payload),
            'opened'    => MailOpened::dispatch($messageId, $recipient, $payload),
            'failed'    => MailFailed::dispatch(
                $messageId,
                $recipient,
                $payload['reason'] ?? null,
                $payload,
            ),
            'bounced'   => MailBounced::dispatch(
                $messageId,
                $recipient,
                $payload['bounce_type'] ?? null,
                $payload,
            ),
            default     => Log::debug("Ahasend webhook: unhandled event [{$event}]"),
        };
    }
}
