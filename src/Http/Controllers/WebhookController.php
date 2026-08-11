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
use GraystackIT\Ahasend\Services\DomainManager;
use GraystackIT\Ahasend\Traits\VerifiesWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    use VerifiesWebhookSignature;

    /**
     * Handle an inbound Ahasend webhook event.
     *
     * Ahasend POSTs a JSON payload to this endpoint on each status change.
     * If a webhook secret is configured the signature is validated first.
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request, config('ahasend.webhook.secret'))) {
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
        // Inbound message routing carries the recipient under `to`; status events use `recipient`/`email`.
        $recipient = (string) ($data['recipient'] ?? $data['email'] ?? $data['to'] ?? '');

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
            'message.routing'        => 'received',
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
            'message.reception',
            'message.routing'         => MailReceived::dispatch($messageId, $recipient, $payload),
            'domain.dns_error'        => $this->handleDomainDnsError($payload),
            'suppression.created'     => SuppressionCreated::dispatch(
                $payload['email'] ?? $recipient,
                $payload['type'] ?? null,
                $payload,
            ),
            default                   => Log::debug("Ahasend webhook: unhandled event [{$event}]"),
        };
    }

    /**
     * Degrade the affected domain and announce the DNS error.
     *
     * This is the only domain event Ahasend sends — there is no counterpart for
     * a domain becoming valid, which is why verification is poll-driven.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleDomainDnsError(array $payload): void
    {
        $domain = (string) ($payload['domain'] ?? '');

        if ($domain !== '') {
            app(DomainManager::class)->markDnsError($domain);
        }

        DomainDnsError::dispatch($domain, $payload);
    }
}
