<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Enums;

enum SuppressionType: string
{
    case HardBounce = 'hard_bounce';
    case SoftBounce = 'soft_bounce';
    case Complaint  = 'complaint';
    case Unsubscribe = 'unsubscribe';
    case Manual     = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::HardBounce  => 'Hard Bounce',
            self::SoftBounce  => 'Soft Bounce',
            self::Complaint   => 'Complaint',
            self::Unsubscribe => 'Unsubscribe',
            self::Manual      => 'Manual',
        };
    }
}
