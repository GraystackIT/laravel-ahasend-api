<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use GraystackIT\Ahasend\Data\InboundMessage;
use GraystackIT\Ahasend\Models\AhasendRoute;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for every verified inbound mail delivered by a route.
 *
 * Listeners must do their work on a queue: Ahasend retries on any non-2xx
 * response, so slow synchronous handling produces duplicates and timeouts.
 * The `deliveryId` is stable across those retries and is the idempotency key.
 */
class InboundMailReceived
{
    use Dispatchable;

    /**
     * @param  AhasendRoute|null  $route  The provisioned route this arrived on, if any.
     *                                    Null for a route managed in the dashboard and
     *                                    verified against a configured secret.
     */
    public function __construct(
        public readonly InboundMessage $message,
        public readonly string $deliveryId,
        public readonly ?AhasendRoute $route = null,
    ) {}
}
