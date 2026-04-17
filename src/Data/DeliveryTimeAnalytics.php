<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents delivery time analytics returned by the Ahasend Reports API.
 */
final class DeliveryTimeAnalytics
{
    /**
     * @param  array<int, array{hour: int, count: int, avg_delivery_seconds: float}>  $byHour
     * @param  array<int, array{day: string, count: int, avg_delivery_seconds: float}>  $byDay
     */
    public function __construct(
        public readonly float  $averageDeliverySeconds,
        public readonly float  $medianDeliverySeconds,
        public readonly int    $totalDelivered,
        public readonly array  $byHour = [],
        public readonly array  $byDay = [],
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            averageDeliverySeconds: (float) ($data['average_delivery_seconds'] ?? 0.0),
            medianDeliverySeconds:  (float) ($data['median_delivery_seconds'] ?? 0.0),
            totalDelivered:         (int) ($data['total_delivered'] ?? 0),
            byHour:                 $data['by_hour'] ?? [],
            byDay:                  $data['by_day'] ?? [],
            from:                   isset($data['from']) ? (string) $data['from'] : null,
            to:                     isset($data['to']) ? (string) $data['to'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'average_delivery_seconds' => $this->averageDeliverySeconds,
            'median_delivery_seconds'  => $this->medianDeliverySeconds,
            'total_delivered'          => $this->totalDelivered,
            'by_hour'                  => $this->byHour,
            'by_day'                   => $this->byDay,
            'from'                     => $this->from,
            'to'                       => $this->to,
        ];
    }
}
