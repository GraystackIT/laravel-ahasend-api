<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Ahasend reports a temporary delivery failure; Ahasend will retry.
 */
class MailTransientError
{
    use Dispatchable;

    /**
     * @param  string|null  $reason  Human-readable diagnostics read from
     *                               `data.delivery_attempt.description` (falling back to
     *                               `.response`); null when no delivery attempt was recorded.
     * @param  array<string, mixed>|null  $deliveryAttempt  Raw `delivery_attempt` object
     *                                                       (smtp_code, enhanced_status_code,
     *                                                       response, description,
     *                                                       classification, command), when present.
     * @param  array<string, mixed>  $payload  Raw webhook payload from Ahasend.
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $recipient,
        public readonly ?string $reason,
        public readonly ?array $deliveryAttempt,
        public readonly array $payload,
    ) {}
}
