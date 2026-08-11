<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Enums\DomainVerifyState;
use GraystackIT\Ahasend\Models\AhasendDomain;
use GraystackIT\Ahasend\Requests\Domains\CheckDomainDnsRequest;
use GraystackIT\Ahasend\Requests\Domains\DeleteDomainRequest;
use GraystackIT\Ahasend\Requests\Routes\CreateRouteRequest;
use Illuminate\Support\Carbon;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * Answer the connector's calls for the duration of a command run.
 *
 * @param  array<class-string, MockResponse>  $responses
 */
function mockConnector(array $responses): void
{
    app(AhasendConnector::class)->withMockClient(new MockClient($responses));
}

it('verifies a pending domain through the poll command', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_1',
        'domain'            => 'acme.at',
        'verify_state'      => DomainVerifyState::Pending,
    ]);

    mockConnector([
        CheckDomainDnsRequest::class => MockResponse::make([
            'id'        => 'dom_1',
            'domain'    => 'acme.at',
            'dns_valid' => true,
        ]),
        CreateRouteRequest::class => MockResponse::make([
            'id'        => 'rt_1',
            'name'      => 'Inbound acme.at',
            'url'       => 'https://app.test/ahasend/inbound/x',
            'recipient' => '*@acme.at',
            'secret'    => 'whsec_1',
        ], 201),
    ]);

    $this->artisan('ahasend:domains:poll')->assertSuccessful();

    expect(AhasendDomain::query()->firstOrFail()->verify_state)->toBe(DomainVerifyState::Verified);
});

it('leaves a domain pending when DNS is still incomplete', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_1',
        'domain'            => 'acme.at',
        'verify_state'      => DomainVerifyState::Pending,
    ]);

    mockConnector([
        CheckDomainDnsRequest::class => MockResponse::make([
            'id'        => 'dom_1',
            'domain'    => 'acme.at',
            'dns_valid' => false,
        ]),
    ]);

    $this->artisan('ahasend:domains:poll')->assertSuccessful();

    expect(AhasendDomain::query()->firstOrFail()->verify_state)->toBe(DomainVerifyState::Failed);
});

it('does not leave a domain stuck in checking when the API fails', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_1',
        'domain'            => 'acme.at',
        'verify_state'      => DomainVerifyState::Pending,
    ]);

    mockConnector([
        CheckDomainDnsRequest::class => MockResponse::make(['error' => 'Service unavailable'], 503),
    ]);

    $this->artisan('ahasend:domains:poll')->assertSuccessful();

    expect(AhasendDomain::query()->firstOrFail()->verify_state)->toBe(DomainVerifyState::Failed);
});

it('skips verified domains when polling', function (): void {
    AhasendDomain::create([
        'ahasend_domain_id' => 'dom_1',
        'domain'            => 'acme.at',
        'verify_state'      => DomainVerifyState::Verified,
        'dns_valid'         => true,
    ]);

    // No mock is registered: any HTTP call would fail the test.
    mockConnector([]);

    $this->artisan('ahasend:domains:poll')->assertSuccessful();
});

it('expires a stale pending domain through the expire command', function (): void {
    config()->set('ahasend.domains.pending_expiry_days', 14);

    $domain = AhasendDomain::create([
        'ahasend_domain_id' => 'dom_1',
        'domain'            => 'typo.at',
        'verify_state'      => DomainVerifyState::Pending,
        'owner_type'        => 'App\\Models\\Organization',
        'owner_id'          => '42',
    ]);

    $domain->forceFill(['created_at' => Carbon::now()->subDays(20)])->save();

    mockConnector([
        DeleteDomainRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('ahasend:domains:expire')->assertSuccessful();

    expect(AhasendDomain::query()->count())->toBe(0);
});
