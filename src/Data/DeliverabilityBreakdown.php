<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents a deliverability breakdown returned by the Ahasend Reports API.
 */
final class DeliverabilityBreakdown
{
    /**
     * @param  array<int, array{domain: string, total: int, delivered: int, bounced: int, rate: float}>  $domains
     */
    public function __construct(
        public readonly int   $totalSent,
        public readonly int   $totalDelivered,
        public readonly int   $totalBounced,
        public readonly float $deliveryRate,
        public readonly array $domains = [],
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $totalSent      = (int) ($data['total_sent'] ?? 0);
        $totalDelivered = (int) ($data['total_delivered'] ?? 0);

        return new self(
            totalSent:      $totalSent,
            totalDelivered: $totalDelivered,
            totalBounced:   (int) ($data['total_bounced'] ?? 0),
            deliveryRate:   isset($data['delivery_rate'])
                ? (float) $data['delivery_rate']
                : ($totalSent > 0 ? round($totalDelivered / $totalSent * 100, 2) : 0.0),
            domains:        $data['domains'] ?? [],
            from:           isset($data['from']) ? (string) $data['from'] : null,
            to:             isset($data['to']) ? (string) $data['to'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_sent'      => $this->totalSent,
            'total_delivered' => $this->totalDelivered,
            'total_bounced'   => $this->totalBounced,
            'delivery_rate'   => $this->deliveryRate,
            'domains'         => $this->domains,
            'from'            => $this->from,
            'to'              => $this->to,
        ];
    }
}
