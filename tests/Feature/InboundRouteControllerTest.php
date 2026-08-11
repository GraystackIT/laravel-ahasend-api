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
function signedHeaders(array $payload, string $secret, string $deliveryId = 'whmsg_1'): array
{
    $timestamp = '1786521600';
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
            && $event->route->is($route)
            && $event->deliveryId === 'whmsg_1';
    });
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

it('returns 404 for an unknown route', function (): void {
    Event::fake([InboundMailReceived::class]);

    $this->postJson('/ahasend/inbound/01JUNKNOWN0000000000000000', routingPayload())
        ->assertNotFound();

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

it('falls back to the message id when no delivery id header is present', function (): void {
    Event::fake([InboundMailReceived::class]);

    // No secret means no signature verification, which is the local-dev setup.
    $route = AhasendRoute::create([
        'public_id'        => '01JROUTEC00000000000000000',
        'ahasend_route_id' => 'rt_789',
        'name'             => 'Inbound dev',
        'recipient'        => '*@dev.test',
        'url'              => 'https://app.test/ahasend/inbound/01JROUTEC00000000000000000',
        'secret'           => null,
    ]);

    $this->postJson("/ahasend/inbound/{$route->public_id}", routingPayload())->assertOk();

    Event::assertDispatched(InboundMailReceived::class, function (InboundMailReceived $event): bool {
        return $event->deliveryId === 'msg-abc';
    });
});
