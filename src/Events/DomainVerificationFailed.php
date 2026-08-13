<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use GraystackIT\Ahasend\Data\DnsRecord;
use GraystackIT\Ahasend\Models\AhasendDomain;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a DNS check ran but required records are still missing or wrong.
 */
class DomainVerificationFailed
{
    use Dispatchable;

    /**
     * @param  list<DnsRecord>  $outstandingRecords  Records that still need attention.
     */
    public function __construct(
        public readonly AhasendDomain $domain,
        public readonly array $outstandingRecords = [],
    ) {}
}
