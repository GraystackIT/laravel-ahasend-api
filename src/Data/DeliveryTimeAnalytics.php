<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents a single time-bucketed entry from the Ahasend delivery time statistics report.
 */
final class DeliveryTimeAnalytics
{
    /**
     * @param  array<int, array{recipient_domain: string, delivery_time: float, count: int}>  $deliveryTimes
     */
    public function __construct(
        public readonly string $fromTimestamp,
        public readonly string $toTimestamp,
        public readonly float  $avgDeliveryTime = 0.0,
        public readonly int    $deliveredCount = 0,
        public readonly array  $deliveryTimes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fromTimestamp:   (string) ($data['from_timestamp'] ?? ''),
            toTimestamp:     (string) ($data['to_timestamp'] ?? ''),
            avgDeliveryTime: (float) ($data['avg_delivery_time'] ?? 0.0),
            deliveredCount:  (int) ($data['delivered_count'] ?? 0),
            deliveryTimes:   $data['delivery_times'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from_timestamp'    => $this->fromTimestamp,
            'to_timestamp'      => $this->toTimestamp,
            'avg_delivery_time' => $this->avgDeliveryTime,
            'delivered_count'   => $this->deliveredCount,
            'delivery_times'    => $this->deliveryTimes,
        ];
    }
}
