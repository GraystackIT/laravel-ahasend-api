<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Events;

use GraystackIT\Ahasend\Data\EmailMessage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an email is successfully submitted to the Ahasend API.
 */
class MailSent
{
    use Dispatchable;

    public function __construct(
        public readonly EmailMessage $message,
        /** The id Ahasend assigned to the first accepted recipient's message. */
        public readonly ?string $ahasendMessageId = null,
        /**
         * Ahasend's id for every accepted recipient, keyed by address — each
         * recipient is a message of its own, and its webhooks carry its own id.
         *
         * @var array<string, string>
         */
        public readonly array $recipientMessageIds = [],
    ) {}
}
