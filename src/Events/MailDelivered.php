<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Ahasend confirms successful delivery via webhook.
 */
class MailDelivered
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $deliveryAttempt  Raw `delivery_attempt` object
     *                                                       (smtp_code, enhanced_status_code,
     *                                                       response, description, command),
     *                                                       when present.
     * @param  array<string, mixed>  $payload  Raw webhook payload from Ahasend.
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $recipient,
        public readonly ?array $deliveryAttempt,
        public readonly array $payload,
    ) {}
}
