<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an unverified domain was removed for exceeding the pending age.
 *
 * The record is already gone locally and remotely, so the event carries plain
 * values rather than the model — enough for a listener to notify the owner.
 */
class DomainExpired
{
    use Dispatchable;

    public function __construct(
        public readonly string $domain,
        public readonly ?string $ownerType = null,
        public readonly ?string $ownerId = null,
    ) {}
}
