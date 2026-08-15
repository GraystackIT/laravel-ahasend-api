<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use GraystackIT\Ahasend\Enums\DomainVerifyState;
use GraystackIT\Ahasend\Models\AhasendDomain;
use Illuminate\Support\Facades\Date;

// Date::use() is global state, so every test here restores the framework default.
afterEach(fn () => Date::useDefault());

it('returns the expiry of a pending domain when the app uses immutable dates', function (): void {
    Date::use(CarbonImmutable::class);

    config()->set('ahasend.domains.pending_expiry_days', 14);

    $domain = AhasendDomain::create([
        'ahasend_domain_id' => 'domain-id',
        'domain'            => 'example.test',
        'verify_state'      => DomainVerifyState::Pending,
    ]);

    expect($domain->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($domain->expiresAt()?->toDateTimeString())
        ->toBe($domain->created_at->addDays(14)->toDateTimeString());
});

it('has no expiry once the domain is verified', function (): void {
    $domain = AhasendDomain::create([
        'ahasend_domain_id' => 'domain-id',
        'domain'            => 'example.test',
        'verify_state'      => DomainVerifyState::Verified,
    ]);

    expect($domain->expiresAt())->toBeNull();
});
