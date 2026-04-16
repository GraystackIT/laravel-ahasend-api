<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Events\MailBounced;
use GraystackIT\Ahasend\Events\MailDelivered;
use GraystackIT\Ahasend\Events\MailFailed;
use GraystackIT\Ahasend\Events\MailOpened;
use Illuminate\Support\Facades\Event;

// ─── Helpers ──────────────────────────────────────────────────────────────

function webhookPayload(string $event, string $messageId = 'msg-1', string $recipient = 'user@example.com'): array
{
    return [
        'event'      => $event,
        'message_id' => $messageId,
        'recipient'  => $recipient,
    ];
}

function postWebhook(array $payload, array $headers = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson(route('ahasend.webhook'), $payload, $headers);
}

// ─── Happy path ───────────────────────────────────────────────────────────

it('returns 200 ok for a delivered event', function (): void {
    Event::fake();

    postWebhook(webhookPayload('delivered'))
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('dispatches MailDelivered event on delivered webhook', function (): void {
    Event::fake([MailDelivered::class]);

    postWebhook(webhookPayload('delivered', 'msg-delivered', 'user@example.com'));

    Event::assertDispatched(MailDelivered::class, function (MailDelivered $event): bool {
        return $event->messageId === 'msg-delivered'
            && $event->recipient === 'user@example.com';
    });
});

it('dispatches MailOpened event on opened webhook', function (): void {
    Event::fake([MailOpened::class]);

    postWebhook(webhookPayload('opened', 'msg-opened'));

    Event::assertDispatched(MailOpened::class, function (MailOpened $event): bool {
        return $event->messageId === 'msg-opened';
    });
});

it('dispatches MailFailed event with reason on failed webhook', function (): void {
    Event::fake([MailFailed::class]);

    postWebhook([
        'event'      => 'failed',
        'message_id' => 'msg-failed',
        'recipient'  => 'user@example.com',
        'reason'     => 'mailbox full',
    ]);

    Event::assertDispatched(MailFailed::class, function (MailFailed $event): bool {
        return $event->messageId === 'msg-failed' && $event->reason === 'mailbox full';
    });
});

it('dispatches MailBounced event with bounce_type on bounced webhook', function (): void {
    Event::fake([MailBounced::class]);

    postWebhook([
        'event'       => 'bounced',
        'message_id'  => 'msg-bounced',
        'recipient'   => 'user@example.com',
        'bounce_type' => 'hard',
    ]);

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

    postWebhook(webhookPayload('click'))->assertOk();

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
        webhookPayload('delivered'),
        ['X-Ahasend-Signature' => 'wrong-signature'],
    )->assertUnauthorized();
});

it('accepts request with correct HMAC signature when secret is set', function (): void {
    $secret  = 'my-secret';
    $payload = webhookPayload('delivered');
    config(['ahasend.webhook.secret' => $secret]);
    Event::fake();

    $body      = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret);

    postWebhook($payload, ['X-Ahasend-Signature' => $signature])->assertOk();
});
