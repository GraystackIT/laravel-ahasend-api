<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Ahasend blocks a send because the recipient is suppressed.
 *
 * Ahasend's payload for this event carries no suppression-reason field, so $suppressionType
 * is always null. To find out why the address was suppressed in the first place, look up the
 * matching {@see SuppressionCreated} event or query the Suppressions API for its `reason`.
 */
class MailSuppressed
{
    use Dispatchable;

    /**
     * @param  string|null  $suppressionType  Always null — see class docblock.
     * @param  array<string, mixed>  $payload  Raw webhook payload from Ahasend.
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $recipient,
        public readonly ?string $suppressionType,
        public readonly array $payload,
    ) {}
}
