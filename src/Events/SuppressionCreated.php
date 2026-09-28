<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Ahasend auto-creates a suppression (e.g. after a hard bounce).
 */
class SuppressionCreated
{
    use Dispatchable;

    /**
     * @param  string|null  $type  Suppression reason as reported by Ahasend (e.g.
     *                             "FBL Complaint Report"), read from the payload's `reason`
     *                             field — Ahasend's suppression.created event has no separate
     *                             `type` field.
     * @param  array<string, mixed>  $payload  Raw webhook payload from Ahasend.
     */
    public function __construct(
        public readonly string $email,
        public readonly ?string $type,
        public readonly array $payload,
    ) {}
}
