<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use GraystackIT\Ahasend\Models\AhasendDomain;
use GraystackIT\Ahasend\Models\AhasendRoute;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once an inbound route exists for a domain, i.e. it can receive mail.
 */
class InboundRouteProvisioned
{
    use Dispatchable;

    public function __construct(
        public readonly AhasendRoute $route,
        public readonly ?AhasendDomain $domain = null,
    ) {}
}
