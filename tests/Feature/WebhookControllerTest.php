<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Events\MailBounced;
use GraystackIT\Ahasend\Events\MailDelivered;
use GraystackIT\Ahasend\Events\MailFailed;
use GraystackIT\Ahasend\Events\MailOpened;
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

    postWebhook(webhookPayload('message.clicked'))->assertOk();

    Event::assertNothingDispatched();
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
