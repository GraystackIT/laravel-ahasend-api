<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use GraystackIT\Ahasend\Models\AhasendDomain;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a DNS check moves a domain to verified for the first time.
 *
 * Only fired on the transition, never on a repeated check of an already
 * verified domain, so listeners can treat it as a one-off.
 */
class DomainVerified
{
    use Dispatchable;

    public function __construct(public readonly AhasendDomain $domain) {}
}
