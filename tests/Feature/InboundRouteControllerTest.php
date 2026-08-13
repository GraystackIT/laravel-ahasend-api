<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Events\InboundMailReceived;
use GraystackIT\Ahasend\Models\AhasendRoute;
use Illuminate\Support\Facades\Event;

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function routingPayload(array $data = []): array
{
    return [
        'type'      => 'message.routing',
        'timestamp' => '2026-08-11T10:00:00Z',
        'data'      => [
            'message_id'  => 'msg-abc',
            'from'        => 'kunde@example.com',
            'to'          => 'support.acme@mail.graystack.one',
            'subject'     => 'Frage zur Rechnung',
            'plain_body'  => 'Guten Tag, …',
            ...$data,
        ],
    ];
}

/**
 * Sign a payload the way Standard Webhooks (and therefore Ahasend) does.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, string>
 */
function signedHeaders(array $payload, string $secret, string $deliveryId = 'whmsg_1', ?int $at = null): array
{
    // Current time by default: the signed timestamp is checked against a
    // tolerance window, so a hard-coded one would start failing on its own.
    $timestamp = (string) ($at ?? time());
    $body      = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', "{$deliveryId}.{$timestamp}.{$body}", $secret, true));

    return [
        'webhook-id'        => $deliveryId,
        'webhook-timestamp' => $timestamp,
        'webhook-signature' => "v1,{$signature}",
    ];
}

function inboundRoute(string $secret = 'whsec_test'): AhasendRoute
{
    return AhasendRoute::create([
        'public_id'        => '01JROUTE000000000000000000',
        'ahasend_route_id' => 'rt_123',
        'name'             => 'Inbound acme.at',
        'recipient'        => '*@mail.graystack.one',
        'url'              => 'https://app.test/ahasend/inbound/01JROUTE000000000000000000',
        'secret'           => $secret,
    ]);
}

it('accepts a correctly signed payload and fires the event', function (): void {
    Event::fake([InboundMailReceived::class]);

    $route   = inboundRoute();
    $payload = routingPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_test'))
        ->postJson("/ahasend/inbound/{$route->public_id}", $payload)
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Event::assertDispatched(InboundMailReceived::class, function (InboundMailReceived $event) use ($route): bool {
        return $event->message->messageId === 'msg-abc'
            && $event->message->from === 'kunde@example.com'
            && $event->route?->is($route) === true
            && $event->deliveredForDomain === 'mail.graystack.one'
            && $event->deliveryId === 'whmsg_1';
    });
});

// ─── Dashboard-managed routes, verified against configured secrets ────────

it('accepts a payload signed with a configured secret', function (): void {
    Event::fake([InboundMailReceived::class]);

    config()->set('ahasend.inbound.secrets', [
        'postbox.graystack.one' => 'whsec_postbox',
        'mail.graystack.one'    => 'whsec_tickets',
    ]);

    $payload = routingPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_tickets'))
        ->postJson('/ahasend/inboundmail', $payload)
        ->assertOk();

    Event::assertDispatched(InboundMailReceived::class, function (InboundMailReceived $event): bool {
        // No stored route: this one was managed in the dashboard.
        return $event->route === null
            && $event->deliveredForDomain === 'mail.graystack.one'
            && $event->message->messageId === 'msg-abc';
    });
});

it('rejects a payload that matches none of the configured secrets', function (): void {
    Event::fake([InboundMailReceived::class]);

    config()->set('ahasend.inbound.secrets', ['mail.graystack.one' => 'whsec_tickets']);

    $payload = routingPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_wrong'))
        ->postJson('/ahasend/inboundmail', $payload)
        ->assertUnauthorized();

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('refuses inbound mail when no secret is configured at all', function (): void {
    Event::fake([InboundMailReceived::class]);

    // An empty secret must not mean "accept anything" on a public endpoint.
    config()->set('ahasend.inbound.secrets', []);

    $payload = routingPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_anything'))
        ->postJson('/ahasend/inboundmail', $payload)
        ->assertStatus(503);

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('rejects a payload signed with the wrong secret', function (): void {
    Event::fake([InboundMailReceived::class]);

    $route   = inboundRoute();
    $payload = routingPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_wrong'))
        ->postJson("/ahasend/inbound/{$route->public_id}", $payload)
        ->assertUnauthorized();

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('rejects a payload with no signature headers at all', function (): void {
    Event::fake([InboundMailReceived::class]);

    $route = inboundRoute();

    $this->postJson("/ahasend/inbound/{$route->public_id}", routingPayload())
        ->assertUnauthorized();

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('rejects a payload signed with another route secret', function (): void {
    Event::fake([InboundMailReceived::class]);

    $route = inboundRoute('whsec_route_a');

    AhasendRoute::create([
        'public_id'        => '01JROUTEB00000000000000000',
        'ahasend_route_id' => 'rt_456',
        'name'             => 'Inbound other.at',
        'recipient'        => '*@other.at',
        'url'              => 'https://app.test/ahasend/inbound/01JROUTEB00000000000000000',
        'secret'           => 'whsec_route_b',
    ]);

    $payload = routingPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_route_b'))
        ->postJson("/ahasend/inbound/{$route->public_id}", $payload)
        ->assertUnauthorized();

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('answers an unknown route the same way as a bad signature', function (): void {
    Event::fake([InboundMailReceived::class]);

    // Same status for unknown and unverified, so route ids cannot be probed.
    $this->postJson('/ahasend/inbound/01JUNKNOWN0000000000000000', routingPayload())
        ->assertUnauthorized();

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('normalises the payload into an InboundMessage', function (): void {
    Event::fake([InboundMailReceived::class]);

    $route   = inboundRoute();
    $payload = routingPayload([
        'in_reply_to' => '<token-abc@mail.graystack.one>',
        'references'  => '<older@mail.graystack.one> <token-abc@mail.graystack.one>',
        'spam_score'  => 2.5,
        'attachments' => [
            [
                'file_name'    => 'rechnung.pdf',
                'content_type' => 'application/pdf',
                'data'         => base64_encode('PDF-BYTES'),
            ],
        ],
    ]);

    $this->withHeaders(signedHeaders($payload, 'whsec_test'))
        ->postJson("/ahasend/inbound/{$route->public_id}", $payload)
        ->assertOk();

    Event::assertDispatched(InboundMailReceived::class, function (InboundMailReceived $event): bool {
        $message = $event->message;

        return $message->inReplyTo === 'token-abc@mail.graystack.one'
            && $message->references === ['older@mail.graystack.one', 'token-abc@mail.graystack.one']
            && $message->spamScore === 2.5
            && count($message->attachments) === 1
            && $message->attachments[0]->contents() === 'PDF-BYTES';
    });
});

it('refuses a provisioned route that has no signing secret', function (): void {
    Event::fake([InboundMailReceived::class]);

    // Without a secret the signature check would wave everything through, and
    // this endpoint is public — refusing is the only safe answer.
    $route = AhasendRoute::create([
        'public_id'        => '01JROUTEC00000000000000000',
        'ahasend_route_id' => 'rt_789',
        'name'             => 'Inbound dev',
        'recipient'        => '*@dev.test',
        'url'              => 'https://app.test/ahasend/inbound/01JROUTEC00000000000000000',
        'secret'           => null,
    ]);

    $this->postJson("/ahasend/inbound/{$route->public_id}", routingPayload())
        ->assertStatus(503);

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('rejects a signature whose timestamp is outside the tolerance', function (): void {
    Event::fake([InboundMailReceived::class]);

    config()->set('ahasend.inbound.tolerance', 300);

    $route   = inboundRoute();
    $payload = routingPayload();

    // Correctly signed, but captured an hour ago: without the window a replay
    // would stay valid forever.
    $this->withHeaders(signedHeaders($payload, 'whsec_test', at: time() - 3600))
        ->postJson("/ahasend/inbound/{$route->public_id}", $payload)
        ->assertUnauthorized();

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('accepts a BCC delivery whose domain appears only in the routed recipient', function (): void {
    Event::fake([InboundMailReceived::class]);

    config()->set('ahasend.inbound.secrets', ['mail.graystack.one' => 'whsec_tickets']);

    // A BCC'd address never shows up in the To header — keying on `to` alone
    // would drop this mail entirely.
    $payload = routingPayload([
        'to'        => 'steuerberater@kanzlei.at',
        'recipient' => 'support.acme@mail.graystack.one',
    ]);

    $this->withHeaders(signedHeaders($payload, 'whsec_tickets'))
        ->postJson('/ahasend/inboundmail', $payload)
        ->assertOk();

    Event::assertDispatched(InboundMailReceived::class, function (InboundMailReceived $event): bool {
        return $event->deliveredForDomain === 'mail.graystack.one';
    });
});

it('picks the secret of the domain the mail was delivered to', function (): void {
    Event::fake([InboundMailReceived::class]);

    config()->set('ahasend.inbound.secrets', [
        'postbox.graystack.one' => 'whsec_postbox',
        'mail.graystack.one'    => 'whsec_tickets',
    ]);

    // Addressed to both platform domains: Ahasend delivers this twice, once per
    // route, and both payloads carry the same address list. Only the signature
    // says which delivery this is.
    $payload = routingPayload([
        'to'        => ['rechnungen.acme@postbox.graystack.one', 'support.acme@mail.graystack.one'],
        'recipient' => 'rechnungen.acme@postbox.graystack.one',
    ]);

    $this->withHeaders(signedHeaders($payload, 'whsec_postbox'))
        ->postJson('/ahasend/inboundmail', $payload)
        ->assertOk();

    Event::assertDispatched(InboundMailReceived::class, function (InboundMailReceived $event): bool {
        return $event->deliveredForDomain === 'postbox.graystack.one';
    });
});
