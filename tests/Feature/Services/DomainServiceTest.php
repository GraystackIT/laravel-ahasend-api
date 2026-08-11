<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Domain;
use GraystackIT\Ahasend\Enums\DnsRecordType;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Domains\CheckDomainDnsRequest;
use GraystackIT\Ahasend\Requests\Domains\CreateDomainRequest;
use GraystackIT\Ahasend\Requests\Domains\DeleteDomainRequest;
use GraystackIT\Ahasend\Requests\Domains\GetDomainRequest;
use GraystackIT\Ahasend\Requests\Domains\ListDomainsRequest;
use GraystackIT\Ahasend\Requests\Domains\UpdateDomainRequest;
use GraystackIT\Ahasend\Services\DomainService;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function domainPayload(array $overrides = []): array
{
    return [
        'id'          => 'dom_123',
        'domain'      => 'acme.at',
        'dns_valid'   => false,
        'dns_records' => [
            ['type' => 'MX', 'host' => 'acme.at', 'content' => 'mx.ahasend.com', 'required' => true, 'propagated' => false],
            ['type' => 'TXT', 'host' => 'acme.at', 'content' => 'v=spf1 include:ahasend.com ~all', 'required' => true, 'propagated' => true],
        ],
        ...$overrides,
    ];
}

function mockedDomainService(MockClient $client): DomainService
{
    $connector = app(AhasendConnector::class);
    $connector->withMockClient($client);

    return new DomainService($connector);
}

// ─── Container resolution ─────────────────────────────────────────────────

it('resolves DomainService from the container', function (): void {
    expect(app(DomainService::class))->toBeInstanceOf(DomainService::class);
});

// ─── create() ─────────────────────────────────────────────────────────────

it('creates a domain and returns the DTO', function (): void {
    $service = mockedDomainService(new MockClient([
        CreateDomainRequest::class => MockResponse::make(domainPayload(), 201),
    ]));

    $domain = $service->create('acme.at');

    expect($domain)->toBeInstanceOf(Domain::class)
        ->and($domain->id)->toBe('dom_123')
        ->and($domain->domain)->toBe('acme.at')
        ->and($domain->dnsRecords)->toHaveCount(2)
        ->and($domain->dnsRecords[0]->type)->toBe(DnsRecordType::Mx);
});

it('sends the domain in the request body', function (): void {
    $client = new MockClient([
        CreateDomainRequest::class => MockResponse::make(domainPayload(), 201),
    ]);

    mockedDomainService($client)->create('acme.at');

    $client->assertSent(function ($request): bool {
        return $request->body()->all() === ['domain' => 'acme.at'];
    });
});

it('sends an Idempotency-Key when creating a domain', function (): void {
    $client = new MockClient([
        CreateDomainRequest::class => MockResponse::make(domainPayload(), 201),
    ]);

    mockedDomainService($client)->create('acme.at', idempotencyKey: 'key-42');

    $client->assertSent(function ($request): bool {
        return $request->headers()->get('Idempotency-Key') === 'key-42';
    });
});

it('rejects a syntactically invalid domain before calling the API', function (): void {
    mockedDomainService(new MockClient([]))->create('not a domain');
})->throws(InvalidArgumentException::class);

it('rejects a domain without a dot', function (): void {
    expect(CreateDomainRequest::isValidDomain('localhost'))->toBeFalse()
        ->and(CreateDomainRequest::isValidDomain('acme.at'))->toBeTrue()
        ->and(CreateDomainRequest::isValidDomain('mail.acme.co.uk'))->toBeTrue();
});

// ─── get() / list() ───────────────────────────────────────────────────────

it('fetches a single domain', function (): void {
    $service = mockedDomainService(new MockClient([
        GetDomainRequest::class => MockResponse::make(domainPayload(['dns_valid' => true])),
    ]));

    expect($service->get('acme.at')->dnsValid)->toBeTrue();
});

it('lists domains and maps them to DTOs', function (): void {
    $service = mockedDomainService(new MockClient([
        ListDomainsRequest::class => MockResponse::make([
            'data' => [domainPayload(), domainPayload(['id' => 'dom_456', 'domain' => 'other.at'])],
            'meta' => ['next' => null],
        ]),
    ]));

    $result = $service->list(limit: 10);

    expect($result['data'])->toHaveCount(2)
        ->and($result['data'][1]->domain)->toBe('other.at')
        ->and($result['meta'])->toBe(['next' => null]);
});

// ─── update() ─────────────────────────────────────────────────────────────

it('updates a domain', function (): void {
    $client = new MockClient([
        UpdateDomainRequest::class => MockResponse::make(domainPayload(['dsn_recipient' => 'dsn@acme.at'])),
    ]);

    $domain = mockedDomainService($client)->update('acme.at', ['dsn_recipient' => 'dsn@acme.at']);

    expect($domain->dsnRecipient)->toBe('dsn@acme.at');

    $client->assertSent(function ($request): bool {
        return $request->body()->all() === ['dsn_recipient' => 'dsn@acme.at'];
    });
});

it('rejects an update without attributes', function (): void {
    mockedDomainService(new MockClient([]))->update('acme.at', []);
})->throws(InvalidArgumentException::class);

// ─── checkDns() ───────────────────────────────────────────────────────────

it('checks DNS and reports the resulting state', function (): void {
    $service = mockedDomainService(new MockClient([
        CheckDomainDnsRequest::class => MockResponse::make(domainPayload([
            'dns_valid'         => true,
            'last_dns_check_at' => '2026-08-11T10:00:00Z',
        ])),
    ]));

    $domain = $service->checkDns('acme.at');

    expect($domain->dnsValid)->toBeTrue()
        ->and($domain->lastDnsCheckAt)->toBe('2026-08-11T10:00:00Z');
});

// ─── delete() ─────────────────────────────────────────────────────────────

it('deletes a domain', function (): void {
    $service = mockedDomainService(new MockClient([
        DeleteDomainRequest::class => MockResponse::make([], 204),
    ]));

    expect($service->delete('acme.at'))->toBeTrue();
});

it('throws AhasendException on an API error', function (): void {
    mockedDomainService(new MockClient([
        CreateDomainRequest::class => MockResponse::make(['error' => 'Conflict'], 409),
    ]))->create('acme.at');
})->throws(AhasendException::class);
