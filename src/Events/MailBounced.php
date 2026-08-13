<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Ahasend reports a bounce via webhook.
 *
 * Two things about the payload, verified against a real bounce:
 *
 * - It carries `message_id_header`, the Message-ID Ahasend assigned to the
 *   outbound mail. That is the same anchor a reply cites in `In-Reply-To`, so
 *   one lookup matches both a reply and a bounce back to their delivery.
 * - It states **no reason**. Why the mail bounced arrives separately, on the
 *   `suppression.created` event, whose `reason` reads e.g. "Invalid Recipient".
 *   An application that needs to tell a hard bounce from a soft one has to
 *   listen there, not here.
 */
class MailBounced
{
    use Dispatchable;

    /**
     * @param  string|null  $bounceType  Absent from every observed payload; kept
     *                                   because a single sample cannot prove it
     *                                   never appears. Treat null as normal.
     * @param  array<string, mixed>  $payload  Raw webhook payload from Ahasend.
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $recipient,
        public readonly ?string $bounceType,
        public readonly array $payload,
    ) {}

    /**
     * The Message-ID of the outbound mail this bounce belongs to.
     */
    public function outboundMessageId(): ?string
    {
        $id = $this->payload['message_id_header'] ?? null;

        return is_string($id) && $id !== '' ? trim($id, '<>') : null;
    }
}
