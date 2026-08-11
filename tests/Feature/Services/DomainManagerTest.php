<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Enums\DomainVerifyState;
use GraystackIT\Ahasend\Events\DomainCreated;
use GraystackIT\Ahasend\Events\DomainDeleted;
use GraystackIT\Ahasend\Events\DomainExpired;
use GraystackIT\Ahasend\Events\DomainVerificationFailed;
use GraystackIT\Ahasend\Events\DomainVerified;
use GraystackIT\Ahasend\Events\InboundRouteProvisioned;
use GraystackIT\Ahasend\Exceptions\DomainAlreadyExistsException;
use GraystackIT\Ahasend\Exceptions\DomainLimitExceededException;
use GraystackIT\Ahasend\Models\AhasendDomain;
use GraystackIT\Ahasend\Models\AhasendRoute;
use GraystackIT\Ahasend\Requests\Domains\CheckDomainDnsRequest;
use GraystackIT\Ahasend\Requests\Domains\CreateDomainRequest;
use GraystackIT\Ahasend\Requests\Domains\DeleteDomainRequest;
use GraystackIT\Ahasend\Requests\Routes\CreateRouteRequest;
use GraystackIT\Ahasend\Requests\Routes\DeleteRouteRequest;
use GraystackIT\Ahasend\Services\DomainManager;
use GraystackIT\Ahasend\Services\DomainService;
use GraystackIT\Ahasend\Services\RouteManager;
use GraystackIT\Ahasend\Services\RouteService;
use GraystackIT\Ahasend\Tests\Fixtures\Owner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Schema::create('test_owners', function ($table): void {
        $table->id();
        $table->string('name')->nullable();
    });

    $this->owner = Owner::create(['name' => 'Acme GmbH']);
});

/**
 * Build a DomainManager whose HTTP calls are answered by the given mocks.
 *
 * @param  array<class-string, MockResponse>  $responses
 */
function manager(array $responses): DomainManager
{
    $connector = app(AhasendConnector::class);
    $connector->withMockClient(new MockClient($responses));

    return new DomainManager(
        new DomainService($connector),
        new RouteManager(new RouteService($connector)),
    );
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiDomain(array $overrides = []): array
{
    return [
        'id'          => 'dom_123',
        'domain'      => 'acme.at',
        'dns_valid'   => false,
        'dns_records' => [
            ['type' => 'MX', 'host' => 'acme.at', 'content' => 'mx.ahasend.com', 'required' => true, 'propagated' => false],
        ],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiRoute(array $overrides = []): array
{
    return [
        'id'        => 'rt_123',
        'name'      => 'Inbound acme.at',
        'url'       => 'https://app.test/ahasend/inbound/x',
        'recipient' => '*@acme.at',
        'secret'    => 'whsec_abc',
        'enabled'   => true,
        ...$overrides,
    ];
}

// ─── add() ────────────────────────────────────────────────────────────────

it('registers a domain as pending and links it to the owner', function (): void {
    Event::fake([DomainCreated::class]);

    $domain = manager([
        CreateDomainRequest::class => MockResponse::make(apiDomain(), 201),
    ])->add('acme.at', $this->owner);

    expect($domain->domain)->toBe('acme.at')
        ->and($domain->verify_state)->toBe(DomainVerifyState::Pending)
        ->and($domain->dns_valid)->toBeFalse()
        ->and($domain->owner_id)->toBe((string) $this->owner->getKey())
        ->and($domain->owner_type)->toBe($this->owner->getMorphClass())
        ->and($domain->dnsRecords())->toHaveCount(1);

    Event::assertDispatched(DomainCreated::class);
});

it('normalises the domain before storing it', function (): void {
    $domain = manager([
        CreateDomainRequest::class => MockResponse::make(apiDomain(), 201),
    ])->add('  ACME.at.  ', $this->owner);

    expect($domain->domain)->toBe('acme.at');
});

it('refuses a second unverified domain for the same owner', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_existing',
        'domain'            => 'pending.at',
        'verify_state'      => DomainVerifyState::Pending,
        'owner_type'        => $this->owner->getMorphClass(),
        'owner_id'          => (string) $this->owner->getKey(),
    ]);

    manager([])->add('acme.at', $this->owner);
})->throws(DomainLimitExceededException::class);

it('names the blocking domain in the exception', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_existing',
        'domain'            => 'typo.at',
        'verify_state'      => DomainVerifyState::Failed,
        'owner_type'        => $this->owner->getMorphClass(),
        'owner_id'          => (string) $this->owner->getKey(),
    ]);

    try {
        manager([])->add('acme.at', $this->owner);
    } catch (DomainLimitExceededException $e) {
        expect($e->blockingDomains)->toBe(['typo.at'])
            ->and($e->getMessage())->toContain('typo.at');

        return;
    }

    $this->fail('Expected a DomainLimitExceededException.');
});

it('allows a further domain once the previous one is verified', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_existing',
        'domain'            => 'verified.at',
        'verify_state'      => DomainVerifyState::Verified,
        'dns_valid'         => true,
        'owner_type'        => $this->owner->getMorphClass(),
        'owner_id'          => (string) $this->owner->getKey(),
    ]);

    $domain = manager([
        CreateDomainRequest::class => MockResponse::make(apiDomain(), 201),
    ])->add('acme.at', $this->owner);

    expect($domain->exists)->toBeTrue();
});

it('does not apply the limit to owner-less shared domains', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_shared',
        'domain'            => 'mail.graystack.one',
        'verify_state'      => DomainVerifyState::Pending,
    ]);

    $domain = manager([
        CreateDomainRequest::class => MockResponse::make(apiDomain(['domain' => 'channel.graystack.one']), 201),
    ])->add('channel.graystack.one');

    expect($domain->owner_type)->toBeNull()
        ->and(AhasendDomain::query()->shared()->count())->toBe(2);
});

it('refuses a domain that is already tracked', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_existing',
        'domain'            => 'acme.at',
        'verify_state'      => DomainVerifyState::Verified,
    ]);

    manager([])->add('acme.at', $this->owner);
})->throws(DomainAlreadyExistsException::class);

// ─── refresh() ────────────────────────────────────────────────────────────

it('verifies a domain and provisions its inbound route', function (): void {
    Event::fake([DomainVerified::class, InboundRouteProvisioned::class]);

    $manager = manager([
        CreateDomainRequest::class  => MockResponse::make(apiDomain(), 201),
        CheckDomainDnsRequest::class => MockResponse::make(apiDomain([
            'dns_valid'         => true,
            'last_dns_check_at' => '2026-08-11T10:00:00Z',
            'dns_records'       => [
                ['type' => 'MX', 'host' => 'acme.at', 'content' => 'mx.ahasend.com', 'required' => true, 'propagated' => true],
            ],
        ])),
        CreateRouteRequest::class   => MockResponse::make(apiRoute(), 201),
    ]);

    $domain = $manager->refresh($manager->add('acme.at', $this->owner));

    expect($domain->verify_state)->toBe(DomainVerifyState::Verified)
        ->and($domain->dns_valid)->toBeTrue()
        ->and($domain->last_dns_check_at)->not->toBeNull()
        ->and($domain->routes()->count())->toBe(1);

    Event::assertDispatched(DomainVerified::class);
    Event::assertDispatched(InboundRouteProvisioned::class);
});

it('stores the route secret encrypted and hides it from arrays', function (): void {
    $manager = manager([
        CreateDomainRequest::class  => MockResponse::make(apiDomain(), 201),
        CheckDomainDnsRequest::class => MockResponse::make(apiDomain(['dns_valid' => true])),
        CreateRouteRequest::class   => MockResponse::make(apiRoute(), 201),
    ]);

    $manager->refresh($manager->add('acme.at', $this->owner));

    $route = AhasendRoute::query()->firstOrFail();

    expect($route->secret)->toBe('whsec_abc')
        ->and($route->toArray())->not->toHaveKey('secret')
        ->and($route->public_id)->not->toBeEmpty();

    // The column must not hold the plaintext.
    $stored = DB::table('ahasend_routes')->value('secret');
    expect($stored)->not->toBe('whsec_abc');
});

it('marks a domain failed and reports the outstanding records', function (): void {
    Event::fake([DomainVerificationFailed::class, InboundRouteProvisioned::class]);

    $manager = manager([
        CreateDomainRequest::class  => MockResponse::make(apiDomain(), 201),
        CheckDomainDnsRequest::class => MockResponse::make(apiDomain(['dns_valid' => false])),
    ]);

    $domain = $manager->refresh($manager->add('acme.at', $this->owner));

    expect($domain->verify_state)->toBe(DomainVerifyState::Failed)
        ->and($domain->outstandingRecords())->toHaveCount(1);

    Event::assertDispatched(DomainVerificationFailed::class);
    Event::assertNotDispatched(InboundRouteProvisioned::class);
});

it('does not provision a second route on a repeated check', function (): void {
    $manager = manager([
        CreateDomainRequest::class  => MockResponse::make(apiDomain(), 201),
        CheckDomainDnsRequest::class => MockResponse::make(apiDomain(['dns_valid' => true])),
        CreateRouteRequest::class   => MockResponse::make(apiRoute(), 201),
    ]);

    $domain = $manager->add('acme.at', $this->owner);
    $manager->refresh($domain);
    $manager->refresh($domain->fresh());

    expect(AhasendRoute::query()->count())->toBe(1);
});

it('fires DomainVerified only on the transition', function (): void {
    $manager = manager([
        CreateDomainRequest::class  => MockResponse::make(apiDomain(), 201),
        CheckDomainDnsRequest::class => MockResponse::make(apiDomain(['dns_valid' => true])),
        CreateRouteRequest::class   => MockResponse::make(apiRoute(), 201),
    ]);

    $domain = $manager->add('acme.at', $this->owner);
    $manager->refresh($domain);

    Event::fake([DomainVerified::class]);
    $manager->refresh($domain->fresh());

    Event::assertNotDispatched(DomainVerified::class);
});

// ─── delete() ─────────────────────────────────────────────────────────────

it('deletes the domain, its routes and the remote object', function (): void {
    Event::fake([DomainDeleted::class]);

    $manager = manager([
        CreateDomainRequest::class  => MockResponse::make(apiDomain(), 201),
        CheckDomainDnsRequest::class => MockResponse::make(apiDomain(['dns_valid' => true])),
        CreateRouteRequest::class   => MockResponse::make(apiRoute(), 201),
        DeleteRouteRequest::class   => MockResponse::make([], 204),
        DeleteDomainRequest::class  => MockResponse::make([], 204),
    ]);

    $domain = $manager->refresh($manager->add('acme.at', $this->owner));
    $manager->delete($domain);

    expect(AhasendDomain::query()->count())->toBe(0)
        ->and(AhasendRoute::query()->count())->toBe(0);

    Event::assertDispatched(DomainDeleted::class);
});

it('frees the slot so the owner can add another domain', function (): void {
    $manager = manager([
        CreateDomainRequest::class => MockResponse::make(apiDomain(), 201),
        DeleteDomainRequest::class => MockResponse::make([], 204),
    ]);

    $manager->delete($manager->add('acme.at', $this->owner));

    expect(fn () => $manager->add('acme.at', $this->owner))->not->toThrow(DomainLimitExceededException::class);
});

// ─── expirePending() ──────────────────────────────────────────────────────

it('expires an unverified domain past the configured age', function (): void {
    Event::fake([DomainExpired::class]);

    config()->set('ahasend.domains.pending_expiry_days', 14);

    $manager = manager([
        CreateDomainRequest::class => MockResponse::make(apiDomain(), 201),
        DeleteDomainRequest::class => MockResponse::make([], 204),
    ]);

    $domain = $manager->add('acme.at', $this->owner);
    $domain->forceFill(['created_at' => Carbon::now()->subDays(15)])->save();

    expect($manager->expirePending())->toBe(1)
        ->and(AhasendDomain::query()->count())->toBe(0);

    Event::assertDispatched(DomainExpired::class);
});

it('keeps a young pending domain and every verified one', function (): void {
    $manager = manager([
        CreateDomainRequest::class => MockResponse::make(apiDomain(), 201),
    ]);

    $manager->add('acme.at', $this->owner);

    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_old_verified',
        'domain'            => 'verified.at',
        'verify_state'      => DomainVerifyState::Verified,
        'owner_type'        => $this->owner->getMorphClass(),
        'owner_id'          => '999',
        'created_at'        => Carbon::now()->subYear(),
    ]);

    expect($manager->expirePending())->toBe(0)
        ->and(AhasendDomain::query()->count())->toBe(2);
});

it('never expires an owner-less shared domain', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_shared',
        'domain'            => 'mail.graystack.one',
        'verify_state'      => DomainVerifyState::Pending,
        'created_at'        => Carbon::now()->subYear(),
    ]);

    expect(manager([])->expirePending())->toBe(0)
        ->and(AhasendDomain::query()->count())->toBe(1);
});

// ─── markDnsError() ───────────────────────────────────────────────────────

it('degrades a verified domain when Ahasend reports a DNS error', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_123',
        'domain'            => 'acme.at',
        'verify_state'      => DomainVerifyState::Verified,
        'dns_valid'         => true,
    ]);

    $domain = manager([])->markDnsError('ACME.at');

    expect($domain?->verify_state)->toBe(DomainVerifyState::Failed)
        ->and($domain?->dns_valid)->toBeFalse();
});

it('ignores a DNS error for a domain it does not track', function (): void {
    expect(manager([])->markDnsError('unknown.at'))->toBeNull();
});

// ─── pollable() ───────────────────────────────────────────────────────────

it('offers only unverified domains inside the poll horizon', function (): void {
    config()->set('ahasend.domains.poll_max_age_days', 14);

    AhasendDomain::create(['ahasend_domain_id' => 'a', 'domain' => 'young.at', 'verify_state' => DomainVerifyState::Pending]);
    AhasendDomain::create(['ahasend_domain_id' => 'b', 'domain' => 'done.at', 'verify_state' => DomainVerifyState::Verified]);

    $old = AhasendDomain::create(['ahasend_domain_id' => 'c', 'domain' => 'old.at', 'verify_state' => DomainVerifyState::Failed]);
    $old->forceFill(['created_at' => Carbon::now()->subDays(30)])->save();

    expect(manager([])->pollable()->pluck('domain')->all())->toBe(['young.at']);
});
