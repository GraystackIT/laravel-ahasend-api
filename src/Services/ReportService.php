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
     * @throws AhasendException
     */
    public function bounceStatistics(
        ?string $from = null,
        ?string $to = null,
        ?string $domain = null,
    ): BounceStatistics {
        Log::info('Ahasend: fetching bounce statistics', compact('from', 'to', 'domain'));

        try {
            $response = $this->connector->send(
                new BounceStatisticsRequest($from, $to, $domain),
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
     * Retrieve deliverability breakdown, optionally by domain.
     *
     * @throws AhasendException
     */
    public function deliverabilityBreakdown(
        ?string $from = null,
        ?string $to = null,
        ?string $domain = null,
    ): DeliverabilityBreakdown {
        Log::info('Ahasend: fetching deliverability breakdown', compact('from', 'to', 'domain'));

        try {
            $response = $this->connector->send(
                new DeliverabilityBreakdownRequest($from, $to, $domain),
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
     * Retrieve delivery time analytics to understand send-time performance.
     *
     * @throws AhasendException
     */
    public function deliveryTimeAnalytics(
        ?string $from = null,
        ?string $to = null,
        ?string $domain = null,
    ): DeliveryTimeAnalytics {
        Log::info('Ahasend: fetching delivery time analytics', compact('from', 'to', 'domain'));

        try {
            $response = $this->connector->send(
                new DeliveryTimeAnalyticsRequest($from, $to, $domain),
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
