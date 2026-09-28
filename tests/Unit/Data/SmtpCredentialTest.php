<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Data\SmtpCredential;

it('constructs a SmtpCredential from an API response array', function (): void {
    $credential = SmtpCredential::fromArray([
        'id'         => 'cred-xyz',
        'name'       => 'My Credential',
        'username'   => 'smtp_user_abc',
        'sandbox'    => true,
        'scope'      => 'scoped',
        'domains'    => ['example.com'],
        'password'   => 'super-secret',
        'created_at' => '2024-01-01T00:00:00Z',
    ]);

    expect($credential->id)->toBe('cred-xyz')
        ->and($credential->name)->toBe('My Credential')
        ->and($credential->username)->toBe('smtp_user_abc')
        ->and($credential->sandbox)->toBeTrue()
        ->and($credential->scope)->toBe('scoped')
        ->and($credential->domains)->toBe(['example.com'])
        ->and($credential->password)->toBe('super-secret')
        ->and($credential->createdAt)->toBe('2024-01-01T00:00:00Z');
});

it('defaults sandbox, scope and domains when not provided', function (): void {
    $credential = SmtpCredential::fromArray([
        'id'       => 'cred-1',
        'name'     => 'Default',
        'username' => 'user',
    ]);

    expect($credential->sandbox)->toBeFalse()
        ->and($credential->scope)->toBe('global')
        ->and($credential->domains)->toBe([]);
});

it('serializes a SmtpCredential to array', function (): void {
    $credential = SmtpCredential::fromArray([
        'id'       => 'cred-1',
        'name'     => 'Test',
        'username' => 'user',
        'scope'    => 'global',
    ]);

    $array = $credential->toArray();

    expect($array)->toBeArray()
        ->and($array['id'])->toBe('cred-1')
        ->and($array['scope'])->toBe('global')
        ->and($array['password'])->toBeNull();
});
