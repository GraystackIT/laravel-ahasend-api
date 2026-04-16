<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Ahasend reports that a recipient opened the email via webhook.
 */
class MailOpened
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  Raw webhook payload from Ahasend.
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $recipient,
        public readonly array $payload,
    ) {}
}
