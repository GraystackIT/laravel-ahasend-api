<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Enums;

enum MessageStatus: string
{
    case Queued     = 'queued';
    case Scheduled  = 'scheduled';
    case Received   = 'received';
    case Delivered  = 'delivered';
    case Deferred   = 'deferred';
    case Bounced    = 'bounced';
    case Failed     = 'failed';
    case Suppressed = 'suppressed';

    /**
     * Fallback for any status string Ahasend returns that isn't one of the cases above.
     *
     * Ahasend documents its message status as an open set of values (new statuses, e.g.
     * sandbox-prefixed variants, can appear without notice), so parsing must never throw on
     * an unrecognized value. Use {@see Message::$rawStatus} to read the original string.
     */
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Queued     => 'Queued',
            self::Scheduled  => 'Scheduled',
            self::Received   => 'Received',
            self::Delivered  => 'Delivered',
            self::Deferred   => 'Deferred',
            self::Bounced    => 'Bounced',
            self::Failed     => 'Failed',
            self::Suppressed => 'Suppressed',
            self::Unknown    => 'Unknown',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Bounced, self::Failed, self::Suppressed], strict: true);
    }
}
