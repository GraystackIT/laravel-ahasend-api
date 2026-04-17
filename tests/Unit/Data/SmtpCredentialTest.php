<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\SmtpCredential;

it('constructs a SmtpCredential from an API response array', function (): void {
    $credential = SmtpCredential::fromArray([
        'id'         => 'cred-xyz',
        'name'       => 'My Credential',
        'username'   => 'smtp_user_abc',
        'host'       => 'smtp.ahasend.com',
        'port'       => 587,
        'password'   => 'super-secret',
        'created_at' => '2024-01-01T00:00:00Z',
    ]);

    expect($credential->id)->toBe('cred-xyz')
        ->and($credential->name)->toBe('My Credential')
        ->and($credential->username)->toBe('smtp_user_abc')
        ->and($credential->host)->toBe('smtp.ahasend.com')
        ->and($credential->port)->toBe(587)
        ->and($credential->password)->toBe('super-secret')
        ->and($credential->createdAt)->toBe('2024-01-01T00:00:00Z');
});

it('defaults host and port when not provided', function (): void {
    $credential = SmtpCredential::fromArray([
        'id'       => 'cred-1',
        'name'     => 'Default',
        'username' => 'user',
    ]);

    expect($credential->host)->toBe('smtp.ahasend.com')
        ->and($credential->port)->toBe(587);
});

it('serializes a SmtpCredential to array', function (): void {
    $credential = SmtpCredential::fromArray([
        'id'       => 'cred-1',
        'name'     => 'Test',
        'username' => 'user',
        'host'     => 'smtp.ahasend.com',
        'port'     => 465,
    ]);

    $array = $credential->toArray();

    expect($array)->toBeArray()
        ->and($array['id'])->toBe('cred-1')
        ->and($array['port'])->toBe(465)
        ->and($array['password'])->toBeNull();
});
