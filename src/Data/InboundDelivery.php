<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

use GraystackIT\Ahasend\Models\AhasendRoute;

/**
 * One verified delivery, as a screen sees it before any listener does.
 */
final readonly class InboundDelivery
{
    /**
     * @param  InboundMessage  $message  The normalised mail.
     * @param  string  $deliveryId  Stable across Ahasend retries; the idempotency key.
     * @param  string  $deliveredForDomain  The domain whose secret verified the payload.
     * @param  AhasendRoute|null  $route  The provisioned route, when it arrived on one.
     * @param  string|null  $channel  The named channel, when it arrived on one.
     */
    public function __construct(
        public InboundMessage $message,
        public string $deliveryId,
        public string $deliveredForDomain,
        public ?AhasendRoute $route = null,
        public ?string $channel = null,
    ) {}
}
