<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\Suppression;

it('constructs a Suppression from an API response array', function (): void {
    $suppression = Suppression::fromArray([
        'id'         => 'sup-abc123',
        'email'      => 'bounce@example.com',
        'domain'     => 'example.com',
        'reason'     => 'User unknown',
        'expires_at' => '2026-12-31T00:00:00Z',
        'created_at' => '2024-06-01T00:00:00Z',
        'protected'  => true,
    ]);

    expect($suppression->id)->toBe('sup-abc123')
        ->and($suppression->email)->toBe('bounce@example.com')
        ->and($suppression->domain)->toBe('example.com')
        ->and($suppression->reason)->toBe('User unknown')
        ->and($suppression->expiresAt)->toBe('2026-12-31T00:00:00Z')
        ->and($suppression->createdAt)->toBe('2024-06-01T00:00:00Z')
        ->and($suppression->protected)->toBeTrue();
});

it('defaults domain, reason and protected when absent', function (): void {
    $suppression = Suppression::fromArray(['email' => 'user@example.com']);

    expect($suppression->domain)->toBeNull()
        ->and($suppression->reason)->toBeNull()
        ->and($suppression->protected)->toBeFalse();
});

it('serializes a Suppression to array', function (): void {
    $suppression = Suppression::fromArray([
        'id'         => 'sup-1',
        'email'      => 'complaint@example.com',
        'expires_at' => '2026-01-01T00:00:00Z',
    ]);

    $array = $suppression->toArray();

    expect($array)->toBeArray()
        ->and($array['id'])->toBe('sup-1')
        ->and($array['email'])->toBe('complaint@example.com')
        ->and($array['expires_at'])->toBe('2026-01-01T00:00:00Z');
});
