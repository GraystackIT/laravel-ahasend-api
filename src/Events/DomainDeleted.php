<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a domain was deliberately removed locally and remotely.
 *
 * The record is already gone, so the event carries plain values.
 */
class DomainDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $domain,
        public readonly ?string $ownerType = null,
        public readonly ?string $ownerId = null,
    ) {}
}
