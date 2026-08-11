<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use GraystackIT\Ahasend\Models\AhasendDomain;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a domain has been registered on the account and stored locally.
 *
 * At this point the DNS records exist but nothing is verified yet.
 */
class DomainCreated
{
    use Dispatchable;

    public function __construct(public readonly AhasendDomain $domain) {}
}
