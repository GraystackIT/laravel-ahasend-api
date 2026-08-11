<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Enums\DomainVerifyState;
use GraystackIT\Ahasend\Models\AhasendDomain;
use GraystackIT\Ahasend\Models\AhasendRoute;
use GraystackIT\Ahasend\Requests\Routes\GetRouteRequest;
use GraystackIT\Ahasend\Requests\Routes\UpdateRouteRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * @param  array<class-string, MockResponse>  $responses
 */
function mockRouteApi(array $responses): MockClient
{
    $client = new MockClient($responses);
    app(AhasendConnector::class)->withMockClient($client);

    return $client;
}

/**
 * A route as the API returns it — note the absence of a secret, which Ahasend
 * only ever hands out at creation time.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dashboardRoute(array $overrides = []): array
{
    return [
        'id'          => 'rt_dashboard',
        'name'        => 'DMS postboxes',
        'url'         => 'https://app.test/ahasend/webhook',
        'recipient'   => '*@in.graystack.app',
        'attachments' => true,
        'enabled'     => true,
        ...$overrides,
    ];
}

it('adopts a dashboard route and repoints it at the inbound endpoint', function (): void {
    $client = mockRouteApi([
        GetRouteRequest::class    => MockResponse::make(dashboardRoute()),
        UpdateRouteRequest::class => MockResponse::make(dashboardRoute()),
    ]);

    $this->artisan('ahasend:routes:import', [
        'route'    => 'rt_dashboard',
        '--secret' => 'whsec_from_dashboard',
    ])->assertSuccessful();

    $route = AhasendRoute::query()->firstOrFail();

    expect($route->ahasend_route_id)->toBe('rt_dashboard')
        ->and($route->secret)->toBe('whsec_from_dashboard')
        ->and($route->public_id)->not->toBeEmpty();

    $client->assertSent(function ($request) use ($route): bool {
        if (! $request instanceof UpdateRouteRequest) {
            return true;
        }

        return $request->body()->all() === [
            'url' => "https://app.test/ahasend/inbound/{$route->public_id}",
        ];
    });
});

it('links the route to a domain it already tracks', function (): void {
    $domain = AhasendDomain::create([
        'ahasend_domain_id' => 'dom_1',
        'domain'            => 'in.graystack.app',
        'verify_state'      => DomainVerifyState::Verified,
        'dns_valid'         => true,
    ]);

    mockRouteApi([
        GetRouteRequest::class    => MockResponse::make(dashboardRoute()),
        UpdateRouteRequest::class => MockResponse::make(dashboardRoute()),
    ]);

    $this->artisan('ahasend:routes:import', [
        'route'    => 'rt_dashboard',
        '--secret' => 'whsec_from_dashboard',
    ])->assertSuccessful();

    expect(AhasendRoute::query()->value('ahasend_domain_id'))->toBe($domain->id);
});

it('refuses an import without a secret', function (): void {
    mockRouteApi([
        GetRouteRequest::class => MockResponse::make(dashboardRoute()),
    ]);

    $this->artisan('ahasend:routes:import', [
        'route'    => 'rt_dashboard',
        '--secret' => '  ',
    ])->assertFailed();

    expect(AhasendRoute::query()->count())->toBe(0);
});

it('leaves the URL alone when asked to', function (): void {
    $client = mockRouteApi([
        GetRouteRequest::class => MockResponse::make(dashboardRoute()),
    ]);

    $this->artisan('ahasend:routes:import', [
        'route'      => 'rt_dashboard',
        '--secret'   => 'whsec_from_dashboard',
        '--keep-url' => true,
    ])->assertSuccessful();

    $client->assertNotSent(UpdateRouteRequest::class);
});

it('does not import the same route twice', function (): void {
    AhasendRoute::create([
        'public_id'        => '01JEXISTING0000000000000000',
        'ahasend_route_id' => 'rt_dashboard',
        'name'             => 'DMS postboxes',
        'recipient'        => '*@in.graystack.app',
        'url'              => 'https://app.test/ahasend/inbound/01JEXISTING0000000000000000',
        'secret'           => 'whsec_existing',
    ]);

    mockRouteApi([
        GetRouteRequest::class => MockResponse::make(dashboardRoute()),
    ]);

    $this->artisan('ahasend:routes:import', [
        'route'    => 'rt_dashboard',
        '--secret' => 'whsec_other',
    ])->assertSuccessful();

    expect(AhasendRoute::query()->count())->toBe(1)
        ->and(AhasendRoute::query()->value('secret'))->toBe('whsec_existing');
});

it('fails cleanly when the route does not exist', function (): void {
    mockRouteApi([
        GetRouteRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    $this->artisan('ahasend:routes:import', [
        'route'    => 'rt_missing',
        '--secret' => 'whsec_x',
    ])->assertFailed();

    expect(AhasendRoute::query()->count())->toBe(0);
});
