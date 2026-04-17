<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\BounceStatistics;
use GraystackIT\Ahasend\Data\DeliverabilityBreakdown;
use GraystackIT\Ahasend\Data\DeliveryTimeAnalytics;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\Reports\BounceStatisticsRequest;
use GraystackIT\Ahasend\Requests\Reports\DeliverabilityBreakdownRequest;
use GraystackIT\Ahasend\Requests\Reports\DeliveryTimeAnalyticsRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

class ReportService
{
    public function __construct(private readonly AhasendConnector $connector) {}

    /**
     * Retrieve bounce statistics for the given date range.
     *
     * @param  string|null  $fromTime     RFC3339 start date-time
     * @param  string|null  $toTime       RFC3339 end date-time
     * @param  string|null  $senderDomain Filter by sending domain
     *
     * @throws AhasendException
     */
    public function bounceStatistics(
        ?string $fromTime = null,
        ?string $toTime = null,
        ?string $senderDomain = null,
    ): BounceStatistics {
        Log::info('Ahasend: fetching bounce statistics', compact('fromTime', 'toTime', 'senderDomain'));

        try {
            $response = $this->connector->send(
                new BounceStatisticsRequest($fromTime, $toTime, $senderDomain),
            );

            return BounceStatistics::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to fetch bounce statistics', [
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * Retrieve deliverability breakdown statistics.
     *
     * @param  string|null  $fromTime         RFC3339 start date-time
     * @param  string|null  $toTime           RFC3339 end date-time
     * @param  string|null  $senderDomain     Filter by sending domain
     * @param  string|null  $recipientDomains Comma-separated recipient domains
     * @param  string|null  $tags             Comma-separated tag filters
     * @param  string|null  $groupBy          hour, day, week, or month
     *
     * @throws AhasendException
     */
    public function deliverabilityBreakdown(
        ?string $fromTime = null,
        ?string $toTime = null,
        ?string $senderDomain = null,
        ?string $recipientDomains = null,
        ?string $tags = null,
        ?string $groupBy = null,
    ): DeliverabilityBreakdown {
        Log::info('Ahasend: fetching deliverability breakdown', compact('fromTime', 'toTime', 'senderDomain'));

        try {
            $response = $this->connector->send(
                new DeliverabilityBreakdownRequest(
                    $fromTime, $toTime, $senderDomain, $recipientDomains, $tags, $groupBy,
                ),
            );

            return DeliverabilityBreakdown::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to fetch deliverability breakdown', [
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * Retrieve delivery time analytics.
     *
     * @param  string|null  $fromTime     RFC3339 start date-time
     * @param  string|null  $toTime       RFC3339 end date-time
     * @param  string|null  $senderDomain Filter by sending domain
     *
     * @throws AhasendException
     */
    public function deliveryTimeAnalytics(
        ?string $fromTime = null,
        ?string $toTime = null,
        ?string $senderDomain = null,
    ): DeliveryTimeAnalytics {
        Log::info('Ahasend: fetching delivery time analytics', compact('fromTime', 'toTime', 'senderDomain'));

        try {
            $response = $this->connector->send(
                new DeliveryTimeAnalyticsRequest($fromTime, $toTime, $senderDomain),
            );

            return DeliveryTimeAnalytics::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to fetch delivery time analytics', [
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }
}
