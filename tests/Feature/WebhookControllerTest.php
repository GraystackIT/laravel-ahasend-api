<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Event;

// ─── Helpers ──────────────────────────────────────────────────────────────

function webhookPayload(string $event, string $messageId = 'msg-1', string $recipient = 'user@example.com', array $extra = []): array
{
    return [
        'type'      => $event,
        'timestamp' => date('c'),
        'data'      => array_merge([
            'id'        => $messageId,
            'recipient' => $recipient,
        ], $extra),
    ];
}

function postWebhook(array $payload, array $headers = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson(route('ahasend.webhook'), $payload, $headers);
}

// ─── Happy path ───────────────────────────────────────────────────────────

it('returns 200 ok for a delivered event', function (): void {
    Event::fake();

    postWebhook(webhookPayload('message.delivered'))
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('dispatches MailDelivered event on delivered webhook', function (): void {
    Event::fake([MailDelivered::class]);

    postWebhook(webhookPayload('message.delivered', 'msg-delivered', 'user@example.com'));

    Event::assertDispatched(MailDelivered::class, function (MailDelivered $event): bool {
        return $event->messageId === 'msg-delivered'
            && $event->recipient === 'user@example.com';
    });
});

it('dispatches MailOpened event on opened webhook', function (): void {
    Event::fake([MailOpened::class]);

    postWebhook(webhookPayload('message.opened', 'msg-opened'));

    Event::assertDispatched(MailOpened::class, function (MailOpened $event): bool {
        return $event->messageId === 'msg-opened';
    });
});

it('dispatches MailFailed event with reason on failed webhook', function (): void {
    Event::fake([MailFailed::class]);

    postWebhook(webhookPayload('message.failed', 'msg-failed', 'user@example.com', ['reason' => 'mailbox full']));

    Event::assertDispatched(MailFailed::class, function (MailFailed $event): bool {
        return $event->messageId === 'msg-failed' && $event->reason === 'mailbox full';
    });
});

it('dispatches MailBounced event with bounce_type on bounced webhook', function (): void {
    Event::fake([MailBounced::class]);

    postWebhook(webhookPayload('message.bounced', 'msg-bounced', 'user@example.com', ['bounce_type' => 'hard']));

    Event::assertDispatched(MailBounced::class, function (MailBounced $event): bool {
        return $event->messageId === 'msg-bounced' && $event->bounceType === 'hard';
    });
});

it('handles unknown event types without error', function (): void {
    Event::fake([
        MailDelivered::class,
        MailOpened::class,
        MailFailed::class,
        MailBounced::class,
    ]);

    postWebhook(webhookPayload('message.completely_unknown'))->assertOk();

    Event::assertNothingDispatched();
});

it('dispatches MailClicked event with url on clicked webhook', function (): void {
    Event::fake([MailClicked::class]);

    postWebhook(webhookPayload('message.clicked', 'msg-clicked', 'user@example.com', ['url' => 'https://example.com']));

    Event::assertDispatched(MailClicked::class, function (MailClicked $event): bool {
        return $event->messageId === 'msg-clicked'
            && $event->recipient === 'user@example.com'
            && $event->url === 'https://example.com';
    });
});

it('dispatches MailSuppressed event with suppression_type on suppressed webhook', function (): void {
    Event::fake([MailSuppressed::class]);

    postWebhook(webhookPayload('message.suppressed', 'msg-suppressed', 'user@example.com', ['suppression_type' => 'unsubscribe']));

    Event::assertDispatched(MailSuppressed::class, function (MailSuppressed $event): bool {
        return $event->messageId === 'msg-suppressed' && $event->suppressionType === 'unsubscribe';
    });
});

it('dispatches MailTransientError event with reason on transient_error webhook', function (): void {
    Event::fake([MailTransientError::class]);

    postWebhook(webhookPayload('message.transient_error', 'msg-transient', 'user@example.com', ['reason' => 'connection timeout']));

    Event::assertDispatched(MailTransientError::class, function (MailTransientError $event): bool {
        return $event->messageId === 'msg-transient' && $event->reason === 'connection timeout';
    });
});

it('dispatches MailReceived event on reception webhook', function (): void {
    Event::fake([MailReceived::class]);

    postWebhook(webhookPayload('message.reception', 'msg-received'));

    Event::assertDispatched(MailReceived::class, function (MailReceived $event): bool {
        return $event->messageId === 'msg-received';
    });
});

it('dispatches MailReceived event on inbound message routing with recipient from the to field', function (): void {
    Event::fake([MailReceived::class]);

    // Real AhaSend message-routing payload: recipient lives in data.to (no data.recipient key).
    postWebhook([
        'type'      => 'message.routing',
        'timestamp' => date('c'),
        'data'      => [
            'id'         => 'msg-routed',
            'from'       => 'customer@gmail.com',
            'to'         => 'support@yourdomain.com',
            'subject'    => 'Help',
            'plain_body' => 'Body',
            'html_body'  => '<p>Body</p>',
            'attachments' => [
                ['filename' => 'a.pdf', 'content_type' => 'application/pdf', 'data' => base64_encode('%PDF')],
            ],
        ],
    ]);

    Event::assertDispatched(MailReceived::class, function (MailReceived $event): bool {
        return $event->messageId === 'msg-routed'
            && $event->recipient === 'support@yourdomain.com'
            && ($event->payload['from'] ?? null) === 'customer@gmail.com';
    });
});

it('dispatches DomainDnsError event on domain dns_error webhook', function (): void {
    Event::fake([DomainDnsError::class]);

    postWebhook([
        'type'      => 'domain.dns_error',
        'timestamp' => date('c'),
        'data'      => ['domain' => 'mail.example.com', 'errors' => ['SPF record missing']],
    ]);

    Event::assertDispatched(DomainDnsError::class, function (DomainDnsError $event): bool {
        return $event->domain === 'mail.example.com';
    });
});

it('dispatches SuppressionCreated event on suppression created webhook', function (): void {
    Event::fake([SuppressionCreated::class]);

    postWebhook([
        'type'      => 'suppression.created',
        'timestamp' => date('c'),
        'data'      => ['email' => 'bounced@example.com', 'type' => 'hard_bounce'],
    ]);

    Event::assertDispatched(SuppressionCreated::class, function (SuppressionCreated $event): bool {
        return $event->email === 'bounced@example.com' && $event->type === 'hard_bounce';
    });
});

// ─── Signature verification ───────────────────────────────────────────────

it('accepts request when no webhook secret is configured', function (): void {
    config(['ahasend.webhook.secret' => null]);
    Event::fake();

    postWebhook(webhookPayload('delivered'))->assertOk();
});

it('rejects request with wrong signature when secret is set', function (): void {
    config(['ahasend.webhook.secret' => 'my-secret']);
    Event::fake();

    postWebhook(
        webhookPayload('message.delivered'),
        [
            'webhook-id'        => 'evt-1',
            'webhook-timestamp' => (string) time(),
            'webhook-signature' => 'v1,wrongsignature',
        ],
    )->assertUnauthorized();
});

it('accepts request with correct Standard Webhooks signature when secret is set', function (): void {
    $secret    = 'my-secret';
    $payload   = webhookPayload('message.delivered');
    $msgId     = 'evt-correct-1';
    $timestamp = (string) time();
    $body      = (string) json_encode($payload);
    $signed    = "{$msgId}.{$timestamp}.{$body}";
    $signature = 'v1,' . base64_encode(hash_hmac('sha256', $signed, $secret, true));

    config(['ahasend.webhook.secret' => $secret]);
    Event::fake();

    postWebhook($payload, [
        'webhook-id'        => $msgId,
        'webhook-timestamp' => $timestamp,
        'webhook-signature' => $signature,
    ])->assertOk();
});
