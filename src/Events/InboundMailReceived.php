<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use GraystackIT\Ahasend\Data\InboundMessage;
use GraystackIT\Ahasend\Models\AhasendRoute;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for every verified inbound mail delivered by a route.
 *
 * With a handoff queue configured this fires in a worker, after the
 * application's screen accepted the delivery. Without one it fires inside the
 * webhook request, and listeners must queue their work themselves: Ahasend
 * retries on any non-2xx response, so slow synchronous handling produces
 * duplicates and timeouts. Either way a delivery can arrive more than once —
 * the `deliveryId` is stable across retries and is the idempotency key.
 */
class InboundMailReceived
{
    use Dispatchable;

    /**
     * @param  string  $deliveredForDomain  The domain whose secret verified this payload.
     *                                      A mail addressed to two of our domains matches
     *                                      two routes and arrives twice, so a consumer has
     *                                      to know which delivery it is looking at;
     *                                      branching on this field gives each delivery
     *                                      exactly one consumer. {@see InboundMessage::$recipient}
     *                                      answers the same question, but this one is
     *                                      authenticated rather than merely transmitted.
     * @param  AhasendRoute|null  $route  The provisioned route this arrived on, if any.
     *                                    Null for a route managed in the dashboard and
     *                                    verified against a configured secret.
     */
    public function __construct(
        public readonly InboundMessage $message,
        public readonly string $deliveryId,
        public readonly string $deliveredForDomain,
        public readonly ?AhasendRoute $route = null,
    ) {}
}
