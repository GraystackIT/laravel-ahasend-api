<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Ahasend reports a hard or soft bounce via webhook.
 */
class MailBounced
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  Raw webhook payload from Ahasend.
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $recipient,
        public readonly ?string $bounceType,
        public readonly array $payload,
    ) {}
}
