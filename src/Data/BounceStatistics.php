<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents bounce statistics returned by the Ahasend Reports API.
 */
final class BounceStatistics
{
    public function __construct(
        public readonly int   $totalSent,
        public readonly int   $hardBounces,
        public readonly int   $softBounces,
        public readonly float $hardBounceRate,
        public readonly float $softBounceRate,
        public readonly float $totalBounceRate,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $totalSent   = (int) ($data['total_sent'] ?? 0);
        $hardBounces = (int) ($data['hard_bounces'] ?? 0);
        $softBounces = (int) ($data['soft_bounces'] ?? 0);
        $total       = $hardBounces + $softBounces;

        return new self(
            totalSent:      $totalSent,
            hardBounces:    $hardBounces,
            softBounces:    $softBounces,
            hardBounceRate: isset($data['hard_bounce_rate'])
                ? (float) $data['hard_bounce_rate']
                : ($totalSent > 0 ? round($hardBounces / $totalSent * 100, 2) : 0.0),
            softBounceRate: isset($data['soft_bounce_rate'])
                ? (float) $data['soft_bounce_rate']
                : ($totalSent > 0 ? round($softBounces / $totalSent * 100, 2) : 0.0),
            totalBounceRate: isset($data['total_bounce_rate'])
                ? (float) $data['total_bounce_rate']
                : ($totalSent > 0 ? round($total / $totalSent * 100, 2) : 0.0),
            from: isset($data['from']) ? (string) $data['from'] : null,
            to:   isset($data['to']) ? (string) $data['to'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_sent'        => $this->totalSent,
            'hard_bounces'      => $this->hardBounces,
            'soft_bounces'      => $this->softBounces,
            'hard_bounce_rate'  => $this->hardBounceRate,
            'soft_bounce_rate'  => $this->softBounceRate,
            'total_bounce_rate' => $this->totalBounceRate,
            'from'              => $this->from,
            'to'                => $this->to,
        ];
    }
}
