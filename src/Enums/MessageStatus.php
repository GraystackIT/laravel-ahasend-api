<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Enums;

enum MessageStatus: string
{
    case Queued    = 'queued';
    case Scheduled = 'scheduled';
    case Sent      = 'sent';
    case Delivered = 'delivered';
    case Opened    = 'opened';
    case Clicked   = 'clicked';
    case Failed    = 'failed';
    case Bounced   = 'bounced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued    => 'Queued',
            self::Scheduled => 'Scheduled',
            self::Sent      => 'Sent',
            self::Delivered => 'Delivered',
            self::Opened    => 'Opened',
            self::Clicked   => 'Clicked',
            self::Failed    => 'Failed',
            self::Bounced   => 'Bounced',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed, self::Bounced, self::Cancelled], strict: true);
    }
}
