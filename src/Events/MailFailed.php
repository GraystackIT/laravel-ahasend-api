<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a message exhausts its retry window without being delivered.
 *
 * This reports retry exhaustion rather than one specific SMTP exchange, so Ahasend's payload
 * for this event never carries a `delivery_attempt` (or any other reason text) — $reason is
 * always null. An immediate permanent rejection by the destination is a different event,
 * {@see MailBounced}, not this one.
 */
class MailFailed
{
    use Dispatchable;

    /**
     * @param  string|null  $reason  Always null — Ahasend's message.failed payload carries no
     *                               per-attempt diagnostic. Kept for API stability in case
     *                               Ahasend adds one.
     * @param  array<string, mixed>  $payload  Raw webhook payload from Ahasend.
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $recipient,
        public readonly ?string $reason,
        public readonly array $payload,
    ) {}
}
