<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Enums;

/**
 * DNS record types Ahasend hands out for a domain.
 */
enum DnsRecordType: string
{
    case Cname = 'CNAME';
    case Txt = 'TXT';
    case Mx = 'MX';

    /**
     * Resolve a record type from an API value, tolerating unexpected casing.
     */
    public static function fromApi(string $value): ?self
    {
        return self::tryFrom(strtoupper(trim($value)));
    }

    /**
     * Whether this record type is what makes a domain able to receive mail.
     */
    public function isInbound(): bool
    {
        return $this === self::Mx;
    }
}
