<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\BounceStatistics;
use GraystackIT\Ahasend\Data\DeliverabilityBreakdown;
use GraystackIT\Ahasend\Data\DeliveryTimeAnalytics;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Reports\BounceStatisticsRequest;
use GraystackIT\Ahasend\Requests\Reports\DeliverabilityBreakdownRequest;
use GraystackIT\Ahasend\Requests\Reports\DeliveryTimeAnalyticsRequest;
use GraystackIT\Ahasend\Services\ReportService;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ─── Container resolution ─────────────────────────────────────────────────

it('resolves ReportService from the container', function (): void {
    expect(app(ReportService::class))->toBeInstanceOf(ReportService::class);
});

// ─── bounceStatistics() ───────────────────────────────────────────────────

it('returns bounce statistics as a list of time-bucketed DTOs', function (): void {
    $mockClient = new MockClient([
        BounceStatisticsRequest::class => MockResponse::make([
            'object' => 'list',
            'data'   => [
                [
                    'from_timestamp' => '2024-01-01T00:00:00Z',
                    'to_timestamp'   => '2024-01-02T00:00:00Z',
                    'bounces'        => [
                        ['classification' => 'hard', 'count' => 5],
                        ['classification' => 'soft', 'count' => 2],
                    ],
                ],
            ],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $buckets = $service->bounceStatistics();

    expect($buckets)->toHaveCount(1)
        ->and($buckets[0])->toBeInstanceOf(BounceStatistics::class)
        ->and($buckets[0]->fromTimestamp)->toBe('2024-01-01T00:00:00Z')
        ->and($buckets[0]->bounces)->toHaveCount(2)
        ->and($buckets[0]->bounces[0]['classification'])->toBe('hard');
});

it('returns an empty array when no bounce data is present', function (): void {
    $mockClient = new MockClient([
        BounceStatisticsRequest::class => MockResponse::make(['object' => 'list', 'data' => []], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $buckets = $service->bounceStatistics();

    expect($buckets)->toBeEmpty();
});

it('throws AhasendException on API error for bounce statistics', function (): void {
    $mockClient = new MockClient([
        BounceStatisticsRequest::class => MockResponse::make(['error' => 'Server error'], 500),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $service->bounceStatistics();
})->throws(AhasendException::class);

// ─── deliverabilityBreakdown() ────────────────────────────────────────────

it('returns deliverability statistics as a list of time-bucketed DTOs', function (): void {
    $mockClient = new MockClient([
        DeliverabilityBreakdownRequest::class => MockResponse::make([
            'object' => 'list',
            'data'   => [
                [
                    'from_timestamp'   => '2024-01-01T00:00:00Z',
                    'to_timestamp'     => '2024-01-02T00:00:00Z',
                    'reception_count'  => 500,
                    'delivered_count'  => 480,
                    'deferred_count'   => 10,
                    'bounced_count'    => 20,
                    'failed_count'     => 0,
                    'suppressed_count' => 0,
                    'opened_count'     => 300,
                    'clicked_count'    => 50,
                ],
            ],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $buckets = $service->deliverabilityBreakdown(fromTime: '2024-01-01T00:00:00Z', toTime: '2024-01-31T23:59:59Z');

    expect($buckets)->toHaveCount(1)
        ->and($buckets[0])->toBeInstanceOf(DeliverabilityBreakdown::class)
        ->and($buckets[0]->receptionCount)->toBe(500)
        ->and($buckets[0]->deliveredCount)->toBe(480)
        ->and($buckets[0]->bouncedCount)->toBe(20);
});

it('throws AhasendException on API error for deliverability breakdown', function (): void {
    $mockClient = new MockClient([
        DeliverabilityBreakdownRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $service->deliverabilityBreakdown();
})->throws(AhasendException::class);

// ─── deliveryTimeAnalytics() ──────────────────────────────────────────────

it('returns delivery time statistics as a list of time-bucketed DTOs', function (): void {
    $mockClient = new MockClient([
        DeliveryTimeAnalyticsRequest::class => MockResponse::make([
            'object' => 'list',
            'data'   => [
                [
                    'from_timestamp'    => '2024-01-15T00:00:00Z',
                    'to_timestamp'      => '2024-01-16T00:00:00Z',
                    'avg_delivery_time' => 44.5,
                    'delivered_count'   => 400,
                    'delivery_times'    => [
                        ['recipient_domain' => 'gmail.com', 'delivery_time' => 38.2, 'count' => 120],
                    ],
                ],
            ],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $buckets = $service->deliveryTimeAnalytics();

    expect($buckets)->toHaveCount(1)
        ->and($buckets[0])->toBeInstanceOf(DeliveryTimeAnalytics::class)
        ->and($buckets[0]->avgDeliveryTime)->toBe(44.5)
        ->and($buckets[0]->deliveredCount)->toBe(400)
        ->and($buckets[0]->deliveryTimes)->toHaveCount(1);
});

it('filters delivery time analytics by domain', function (): void {
    $mockClient = new MockClient([
        DeliveryTimeAnalyticsRequest::class => MockResponse::make(['object' => 'list', 'data' => []], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $buckets = $service->deliveryTimeAnalytics(senderDomain: 'outlook.com');

    expect($buckets)->toBeEmpty();
});

it('throws AhasendException on API error for delivery time analytics', function (): void {
    $mockClient = new MockClient([
        DeliveryTimeAnalyticsRequest::class => MockResponse::make(['error' => 'Server error'], 500),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $service->deliveryTimeAnalytics();
})->throws(AhasendException::class);
