<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\Suppression;
use GraystackIT\Ahasend\Enums\SuppressionType;

it('constructs a Suppression from an API response array', function (): void {
    $suppression = Suppression::fromArray([
        'email'      => 'bounce@example.com',
        'type'       => 'hard_bounce',
        'reason'     => 'User unknown',
        'created_at' => '2024-06-01T00:00:00Z',
    ]);

    expect($suppression->email)->toBe('bounce@example.com')
        ->and($suppression->type)->toBe(SuppressionType::HardBounce)
        ->and($suppression->reason)->toBe('User unknown')
        ->and($suppression->createdAt)->toBe('2024-06-01T00:00:00Z');
});

it('defaults to manual type when type is absent', function (): void {
    $suppression = Suppression::fromArray(['email' => 'user@example.com']);

    expect($suppression->type)->toBe(SuppressionType::Manual);
});

it('serializes a Suppression to array', function (): void {
    $suppression = Suppression::fromArray([
        'email' => 'complaint@example.com',
        'type'  => 'complaint',
    ]);

    $array = $suppression->toArray();

    expect($array)->toBeArray()
        ->and($array['email'])->toBe('complaint@example.com')
        ->and($array['type'])->toBe('complaint');
});

it('provides a human-readable label for each suppression type', function (): void {
    expect(SuppressionType::HardBounce->label())->toBe('Hard Bounce')
        ->and(SuppressionType::SoftBounce->label())->toBe('Soft Bounce')
        ->and(SuppressionType::Complaint->label())->toBe('Complaint')
        ->and(SuppressionType::Unsubscribe->label())->toBe('Unsubscribe')
        ->and(SuppressionType::Manual->label())->toBe('Manual');
});
