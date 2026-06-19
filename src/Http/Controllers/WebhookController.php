<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Http\Controllers;

use GraystackIT\Ahasend\Events\DomainDnsError;
use GraystackIT\Ahasend\Events\MailBounced;
use GraystackIT\Ahasend\Events\MailClicked;
use GraystackIT\Ahasend\Events\MailDelivered;
use GraystackIT\Ahasend\Events\MailFailed;
use GraystackIT\Ahasend\Events\MailOpened;
use GraystackIT\Ahasend\Events\MailReceived;
use GraystackIT\Ahasend\Events\MailSuppressed;
use GraystackIT\Ahasend\Events\MailTransientError;
use GraystackIT\Ahasend\Events\SuppressionCreated;
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

        // Standard Webhooks format: { type, timestamp, data: { ... } }
        $event = (string) ($payload['type'] ?? '');
        /** @var array<string, mixed> $data */
        $data      = (array) ($payload['data'] ?? $payload);
        $messageId = (string) ($data['id'] ?? $data['message_id'] ?? '');
        $recipient = (string) ($data['recipient'] ?? $data['email'] ?? '');

        Log::info('Ahasend webhook received', [
            'event'      => $event,
            'message_id' => $messageId,
            'recipient'  => $recipient,
        ]);

        $this->persistStatusUpdate($messageId, $event, $data);
        $this->dispatchEvent($event, $messageId, $recipient, $data);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify the webhook signature using Standard Webhooks format.
     *
     * The signed content is "{webhook-id}.{webhook-timestamp}.{raw-body}".
     * The `webhook-signature` header contains one or more space-separated
     * "v1,{base64}" signatures; any matching signature is accepted.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('ahasend.webhook.secret');

        if ($secret === null || $secret === '') {
            return true;
        }

        $msgId        = (string) $request->header('webhook-id', '');
        $msgTimestamp = (string) $request->header('webhook-timestamp', '');
        $sigHeader    = (string) $request->header('webhook-signature', '');

        if ($msgId === '' || $msgTimestamp === '' || $sigHeader === '') {
            return false;
        }

        $signed   = "{$msgId}.{$msgTimestamp}.{$request->getContent()}";
        $expected = base64_encode(hash_hmac('sha256', $signed, $secret, true));

        foreach (explode(' ', $sigHeader) as $part) {
            $parts = explode(',', $part, 2);
            if (count($parts) === 2 && hash_equals($expected, $parts[1])) {
                return true;
            }
        }

        return false;
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

        if ($messageId === '') {
            return;
        }

        $status = match ($event) {
            'message.delivered'      => 'delivered',
            'message.opened'         => 'opened',
            'message.clicked'        => 'clicked',
            'message.failed'         => 'failed',
            'message.bounced'        => 'bounced',
            'message.suppressed'     => 'suppressed',
            'message.transient_error' => 'transient_error',
            'message.reception'      => 'received',
            default                  => $event,
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
            'message.delivered'       => MailDelivered::dispatch($messageId, $recipient, $payload),
            'message.opened'          => MailOpened::dispatch($messageId, $recipient, $payload),
            'message.clicked'         => MailClicked::dispatch(
                $messageId,
                $recipient,
                $payload['url'] ?? null,
                $payload,
            ),
            'message.failed'          => MailFailed::dispatch(
                $messageId,
                $recipient,
                $payload['reason'] ?? null,
                $payload,
            ),
            'message.bounced'         => MailBounced::dispatch(
                $messageId,
                $recipient,
                $payload['bounce_type'] ?? null,
                $payload,
            ),
            'message.suppressed'      => MailSuppressed::dispatch(
                $messageId,
                $recipient,
                $payload['suppression_type'] ?? null,
                $payload,
            ),
            'message.transient_error' => MailTransientError::dispatch(
                $messageId,
                $recipient,
                $payload['reason'] ?? null,
                $payload,
            ),
            'message.reception'       => MailReceived::dispatch($messageId, $recipient, $payload),
            'domain.dns_error'        => DomainDnsError::dispatch(
                $payload['domain'] ?? '',
                $payload,
            ),
            'suppression.created'     => SuppressionCreated::dispatch(
                $payload['email'] ?? $recipient,
                $payload['type'] ?? null,
                $payload,
            ),
            default                   => Log::debug("Ahasend webhook: unhandled event [{$event}]"),
        };
    }
}
