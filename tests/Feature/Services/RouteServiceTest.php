<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Route;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Routes\CreateRouteRequest;
use GraystackIT\Ahasend\Requests\Routes\DeleteRouteRequest;
use GraystackIT\Ahasend\Requests\Routes\GetRouteRequest;
use GraystackIT\Ahasend\Requests\Routes\ListRoutesRequest;
use GraystackIT\Ahasend\Requests\Routes\UpdateRouteRequest;
use GraystackIT\Ahasend\Services\RouteService;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function routePayload(array $overrides = []): array
{
    return [
        'id'                  => 'rt_123',
        'name'                => 'Inbound acme.at',
        'url'                 => 'https://app.test/ahasend/inbound/01J000',
        'recipient'           => '*@acme.at',
        'attachments'         => true,
        'headers'             => true,
        'strip_replies'       => true,
        'group_by_message_id' => true,
        'enabled'             => true,
        ...$overrides,
    ];
}

function mockedRouteService(MockClient $client): RouteService
{
    $connector = app(AhasendConnector::class);
    $connector->withMockClient($client);

    return new RouteService($connector);
}

it('resolves RouteService from the container', function (): void {
    expect(app(RouteService::class))->toBeInstanceOf(RouteService::class);
});

it('creates a route and returns the secret exactly once', function (): void {
    $service = mockedRouteService(new MockClient([
        CreateRouteRequest::class => MockResponse::make(routePayload(['secret' => 'whsec_abc']), 201),
    ]));

    $route = $service->create('Inbound acme.at', 'https://app.test/ahasend/inbound/01J000', '*@acme.at');

    expect($route)->toBeInstanceOf(Route::class)
        ->and($route->id)->toBe('rt_123')
        ->and($route->secret)->toBe('whsec_abc');
});

it('sends every route option in the request body', function (): void {
    $client = new MockClient([
        CreateRouteRequest::class => MockResponse::make(routePayload(), 201),
    ]);

    mockedRouteService($client)->create(
        name: 'Inbound acme.at',
        url: 'https://app.test/ahasend/inbound/01J000',
        recipient: '*@acme.at',
        includeAttachments: true,
        includeHeaders: true,
        groupByMessageId: true,
        stripReplies: true,
    );

    $client->assertSent(function ($request): bool {
        return $request->body()->all() === [
            'name'                => 'Inbound acme.at',
            'url'                 => 'https://app.test/ahasend/inbound/01J000',
            'recipient'           => '*@acme.at',
            'attachments'         => true,
            'headers'             => true,
            'group_by_message_id' => true,
            'strip_replies'       => true,
            'enabled'             => true,
        ];
    });
});

it('rejects an invalid route URL', function (): void {
    mockedRouteService(new MockClient([]))->create('Inbound', 'not-a-url', '*@acme.at');
})->throws(InvalidArgumentException::class);

it('rejects an empty recipient pattern', function (): void {
    mockedRouteService(new MockClient([]))->create('Inbound', 'https://app.test/x', '  ');
})->throws(InvalidArgumentException::class);

it('derives the domain from the recipient pattern', function (): void {
    expect(Route::fromArray(routePayload())->domain())->toBe('acme.at')
        ->and(Route::fromArray(routePayload(['recipient' => 'broken']))->domain())->toBeNull();
});

it('fetches, lists, updates and deletes routes', function (): void {
    $service = mockedRouteService(new MockClient([
        GetRouteRequest::class    => MockResponse::make(routePayload()),
        ListRoutesRequest::class  => MockResponse::make(['data' => [routePayload()], 'meta' => []]),
        UpdateRouteRequest::class => MockResponse::make(routePayload(['enabled' => false])),
        DeleteRouteRequest::class => MockResponse::make([], 204),
    ]));

    expect($service->get('rt_123')->recipient)->toBe('*@acme.at')
        ->and($service->list()['data'])->toHaveCount(1)
        ->and($service->update('rt_123', ['enabled' => false])->enabled)->toBeFalse()
        ->and($service->delete('rt_123'))->toBeTrue();
});

it('throws AhasendException on an API error', function (): void {
    mockedRouteService(new MockClient([
        CreateRouteRequest::class => MockResponse::make(['error' => 'Domain not verified'], 422),
    ]))->create('Inbound', 'https://app.test/x', '*@acme.at');
})->throws(AhasendException::class);
