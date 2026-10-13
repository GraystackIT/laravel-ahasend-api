<?php

declare(strict_types=1);

uses(GraystackIT\Ahasend\Tests\TestCase::class)->in('Feature', 'Unit');

// Feature tests touch the package's own tables; unit tests stay pure.
uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature');

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
