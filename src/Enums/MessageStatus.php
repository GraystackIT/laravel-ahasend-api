<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Enums;

enum MessageStatus: string
{
    case Queued     = 'queued';
    case Scheduled  = 'scheduled';
    case Delivered  = 'delivered';
    case Deferred   = 'deferred';
    case Bounced    = 'bounced';
    case Failed     = 'failed';
    case Suppressed = 'suppressed';

    public function label(): string
    {
        return match ($this) {
            self::Queued     => 'Queued',
            self::Scheduled  => 'Scheduled',
            self::Delivered  => 'Delivered',
            self::Deferred   => 'Deferred',
            self::Bounced    => 'Bounced',
            self::Failed     => 'Failed',
            self::Suppressed => 'Suppressed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Bounced, self::Failed, self::Suppressed], strict: true);
    }
}
