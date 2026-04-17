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

it('returns bounce statistics as a typed DTO', function (): void {
    $mockClient = new MockClient([
        BounceStatisticsRequest::class => MockResponse::make([
            'total_sent'        => 1000,
            'hard_bounces'      => 50,
            'soft_bounces'      => 20,
            'hard_bounce_rate'  => 5.0,
            'soft_bounce_rate'  => 2.0,
            'total_bounce_rate' => 7.0,
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $stats   = $service->bounceStatistics();

    expect($stats)->toBeInstanceOf(BounceStatistics::class)
        ->and($stats->totalSent)->toBe(1000)
        ->and($stats->hardBounces)->toBe(50)
        ->and($stats->softBounces)->toBe(20)
        ->and($stats->hardBounceRate)->toBe(5.0)
        ->and($stats->totalBounceRate)->toBe(7.0);
});

it('calculates bounce rates from raw counts when rates are absent', function (): void {
    $mockClient = new MockClient([
        BounceStatisticsRequest::class => MockResponse::make([
            'total_sent'   => 200,
            'hard_bounces' => 10,
            'soft_bounces' => 5,
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $stats   = $service->bounceStatistics();

    expect($stats->hardBounceRate)->toBe(5.0)
        ->and($stats->softBounceRate)->toBe(2.5)
        ->and($stats->totalBounceRate)->toBe(7.5);
});

it('returns zero rates when total_sent is zero', function (): void {
    $mockClient = new MockClient([
        BounceStatisticsRequest::class => MockResponse::make([
            'total_sent'   => 0,
            'hard_bounces' => 0,
            'soft_bounces' => 0,
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new ReportService($connector);
    $stats   = $service->bounceStatistics();

    expect($stats->hardBounceRate)->toBe(0.0)
        ->and($stats->totalBounceRate)->toBe(0.0);
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

it('returns deliverability breakdown as a typed DTO', function (): void {
    $mockClient = new MockClient([
        DeliverabilityBreakdownRequest::class => MockResponse::make([
            'total_sent'      => 500,
            'total_delivered' => 480,
            'total_bounced'   => 20,
            'delivery_rate'   => 96.0,
            'domains'         => [
                ['domain' => 'gmail.com', 'total' => 300, 'delivered' => 295, 'bounced' => 5, 'rate' => 98.3],
            ],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service    = new ReportService($connector);
    $breakdown  = $service->deliverabilityBreakdown('2024-01-01', '2024-01-31');

    expect($breakdown)->toBeInstanceOf(DeliverabilityBreakdown::class)
        ->and($breakdown->totalSent)->toBe(500)
        ->and($breakdown->totalDelivered)->toBe(480)
        ->and($breakdown->deliveryRate)->toBe(96.0)
        ->and($breakdown->domains)->toHaveCount(1)
        ->and($breakdown->domains[0]['domain'])->toBe('gmail.com');
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

it('returns delivery time analytics as a typed DTO', function (): void {
    $mockClient = new MockClient([
        DeliveryTimeAnalyticsRequest::class => MockResponse::make([
            'average_delivery_seconds' => 45.7,
            'median_delivery_seconds'  => 30.0,
            'total_delivered'          => 900,
            'by_hour'                  => [
                ['hour' => 9, 'count' => 120, 'avg_delivery_seconds' => 38.2],
                ['hour' => 14, 'count' => 200, 'avg_delivery_seconds' => 42.1],
            ],
            'by_day'                   => [
                ['day' => '2024-01-15', 'count' => 400, 'avg_delivery_seconds' => 44.5],
            ],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service   = new ReportService($connector);
    $analytics = $service->deliveryTimeAnalytics();

    expect($analytics)->toBeInstanceOf(DeliveryTimeAnalytics::class)
        ->and($analytics->averageDeliverySeconds)->toBe(45.7)
        ->and($analytics->medianDeliverySeconds)->toBe(30.0)
        ->and($analytics->totalDelivered)->toBe(900)
        ->and($analytics->byHour)->toHaveCount(2)
        ->and($analytics->byDay)->toHaveCount(1);
});

it('filters delivery time analytics by domain', function (): void {
    $mockClient = new MockClient([
        DeliveryTimeAnalyticsRequest::class => MockResponse::make([
            'average_delivery_seconds' => 22.0,
            'median_delivery_seconds'  => 18.0,
            'total_delivered'          => 100,
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service   = new ReportService($connector);
    $analytics = $service->deliveryTimeAnalytics(domain: 'outlook.com');

    expect($analytics->averageDeliverySeconds)->toBe(22.0);
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
