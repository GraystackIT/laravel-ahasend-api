<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Enums;

/**
 * Lifecycle state of a locally tracked Ahasend domain.
 */
enum DomainVerifyState: string
{
    /** Created remotely, DNS records handed out, nothing verified yet. */
    case Pending = 'pending';

    /** A DNS check is currently in flight. */
    case Checking = 'checking';

    /** Ahasend reports every required record as valid. */
    case Verified = 'verified';

    /** A DNS check ran and at least one required record is missing or wrong. */
    case Failed = 'failed';

    /**
     * States that do not count as a finished verification.
     *
     * @return list<self>
     */
    public static function unverified(): array
    {
        return [self::Pending, self::Checking, self::Failed];
    }

    /**
     * Whether this state blocks an owner from adding another domain.
     */
    public function blocksFurtherDomains(): bool
    {
        return $this !== self::Verified;
    }
}
