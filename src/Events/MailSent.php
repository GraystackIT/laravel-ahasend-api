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
        /** The message_id returned by the Ahasend API. */
        public readonly ?string $ahasendMessageId = null,
    ) {}
}
