<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\Suppression;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Suppressions\CreateSuppressionRequest;
use GraystackIT\Ahasend\Requests\Suppressions\DeleteAllSuppressionsRequest;
use GraystackIT\Ahasend\Requests\Suppressions\DeleteSuppressionRequest;
use GraystackIT\Ahasend\Requests\Suppressions\ListSuppressionsRequest;
use GraystackIT\Ahasend\Services\SuppressionService;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ─── Container resolution ─────────────────────────────────────────────────

it('resolves SuppressionService from the container', function (): void {
    expect(app(SuppressionService::class))->toBeInstanceOf(SuppressionService::class);
});

// ─── create() ─────────────────────────────────────────────────────────────

it('creates a suppression and returns the DTO', function (): void {
    $mockClient = new MockClient([
        CreateSuppressionRequest::class => MockResponse::make([
            'email'      => 'bounce@example.com',
            'reason'     => 'User unknown',
            'created_at' => '2024-01-01T00:00:00Z',
        ], 201),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service     = new SuppressionService($connector);
    $suppression = $service->create('bounce@example.com', '2026-12-31T00:00:00Z', 'User unknown');

    expect($suppression)->toBeInstanceOf(Suppression::class)
        ->and($suppression->email)->toBe('bounce@example.com')
        ->and($suppression->reason)->toBe('User unknown');
});

it('throws InvalidArgumentException for an invalid email in create', function (): void {
    $service = new SuppressionService(app(AhasendConnector::class));
    $service->create('not-an-email', '2026-12-31T00:00:00Z');
})->throws(\InvalidArgumentException::class);

it('throws AhasendException on API error during suppression create', function (): void {
    $mockClient = new MockClient([
        CreateSuppressionRequest::class => MockResponse::make(['error' => 'Bad request'], 400),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SuppressionService($connector);
    $service->create('user@example.com', '2026-12-31T00:00:00Z');
})->throws(AhasendException::class);

// ─── list() ───────────────────────────────────────────────────────────────

it('lists suppressions and returns Suppression DTOs', function (): void {
    $mockClient = new MockClient([
        ListSuppressionsRequest::class => MockResponse::make([
            'data' => [
                ['email' => 'a@example.com'],
                ['email' => 'b@example.com'],
            ],
            'meta' => ['total' => 2],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SuppressionService($connector);
    $result  = $service->list();

    expect($result['data'])->toHaveCount(2)
        ->and($result['data'][0])->toBeInstanceOf(Suppression::class)
        ->and($result['data'][0]->email)->toBe('a@example.com')
        ->and($result['meta']['total'])->toBe(2);
});

it('filters suppressions by domain', function (): void {
    $mockClient = new MockClient([
        ListSuppressionsRequest::class => MockResponse::make([
            'data' => [],
            'meta' => [],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SuppressionService($connector);
    $result  = $service->list(domain: 'example.com');

    expect($result['data'])->toBeEmpty();
});

it('throws AhasendException on API error when listing suppressions', function (): void {
    $mockClient = new MockClient([
        ListSuppressionsRequest::class => MockResponse::make(['error' => 'Server error'], 500),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SuppressionService($connector);
    $service->list();
})->throws(AhasendException::class);

// ─── delete() ─────────────────────────────────────────────────────────────

it('deletes a specific suppression by email', function (): void {
    $mockClient = new MockClient([
        DeleteSuppressionRequest::class => MockResponse::make([], 204),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SuppressionService($connector);
    $result  = $service->delete('user@example.com');

    expect($result)->toBeTrue();
});

it('throws InvalidArgumentException for an invalid email in delete', function (): void {
    $service = new SuppressionService(app(AhasendConnector::class));
    $service->delete('bad-email');
})->throws(\InvalidArgumentException::class);

it('throws AhasendException when deleting a non-suppressed email', function (): void {
    $mockClient = new MockClient([
        DeleteSuppressionRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SuppressionService($connector);
    $service->delete('notfound@example.com');
})->throws(AhasendException::class);

// ─── deleteAll() ──────────────────────────────────────────────────────────

it('deletes all suppressions', function (): void {
    $mockClient = new MockClient([
        DeleteAllSuppressionsRequest::class => MockResponse::make([], 204),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SuppressionService($connector);
    $result  = $service->deleteAll();

    expect($result)->toBeTrue();
});

it('throws AhasendException on API error when deleting all suppressions', function (): void {
    $mockClient = new MockClient([
        DeleteAllSuppressionsRequest::class => MockResponse::make(['error' => 'Server error'], 500),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SuppressionService($connector);
    $service->deleteAll();
})->throws(AhasendException::class);
