<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Support;

/**
 * One named inbound endpoint: the domain it receives for, the secret that
 * proves a delivery came from that domain's route, and the queue it hands off to.
 */
final readonly class InboundChannel
{
    /**
     * @param  string  $name  The URL segment, e.g. `payslip` for `/ahasend/inboundmail/payslip`.
     * @param  string  $domain  Lower-cased receiving domain.
     * @param  string|null  $secret  The route's signing secret; null falls back to `ahasend.inbound.secrets`.
     * @param  string|null  $queue  Queue to hand deliveries to; null uses `ahasend.inbound.handoff.queue`.
     */
    public function __construct(
        public string $name,
        public string $domain,
        public ?string $secret = null,
        public ?string $queue = null,
    ) {}
}
