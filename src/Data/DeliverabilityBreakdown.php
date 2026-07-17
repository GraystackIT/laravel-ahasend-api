<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents a single time-bucketed entry from the Ahasend deliverability statistics report.
 */
final class DeliverabilityBreakdown
{
    public function __construct(
        public readonly string $fromTimestamp,
        public readonly string $toTimestamp,
        public readonly int    $receptionCount = 0,
        public readonly int    $deliveredCount = 0,
        public readonly int    $deferredCount = 0,
        public readonly int    $bouncedCount = 0,
        public readonly int    $failedCount = 0,
        public readonly int    $suppressedCount = 0,
        public readonly int    $openedCount = 0,
        public readonly int    $clickedCount = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fromTimestamp:   (string) ($data['from_timestamp'] ?? ''),
            toTimestamp:     (string) ($data['to_timestamp'] ?? ''),
            receptionCount:  (int) ($data['reception_count'] ?? 0),
            deliveredCount:  (int) ($data['delivered_count'] ?? 0),
            deferredCount:   (int) ($data['deferred_count'] ?? 0),
            bouncedCount:    (int) ($data['bounced_count'] ?? 0),
            failedCount:     (int) ($data['failed_count'] ?? 0),
            suppressedCount: (int) ($data['suppressed_count'] ?? 0),
            openedCount:     (int) ($data['opened_count'] ?? 0),
            clickedCount:    (int) ($data['clicked_count'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from_timestamp'   => $this->fromTimestamp,
            'to_timestamp'     => $this->toTimestamp,
            'reception_count'  => $this->receptionCount,
            'delivered_count'  => $this->deliveredCount,
            'deferred_count'   => $this->deferredCount,
            'bounced_count'    => $this->bouncedCount,
            'failed_count'     => $this->failedCount,
            'suppressed_count' => $this->suppressedCount,
            'opened_count'     => $this->openedCount,
            'clicked_count'    => $this->clickedCount,
        ];
    }
}
