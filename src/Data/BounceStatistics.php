<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents a single time-bucketed entry from the Ahasend bounce statistics report.
 */
final class BounceStatistics
{
    /**
     * @param  array<int, array{classification: string, count: int}>  $bounces
     */
    public function __construct(
        public readonly string $fromTimestamp,
        public readonly string $toTimestamp,
        public readonly array  $bounces = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fromTimestamp: (string) ($data['from_timestamp'] ?? ''),
            toTimestamp:   (string) ($data['to_timestamp'] ?? ''),
            bounces:       $data['bounces'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from_timestamp' => $this->fromTimestamp,
            'to_timestamp'   => $this->toTimestamp,
            'bounces'        => $this->bounces,
        ];
    }
}
