<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests;

use GraystackIT\Ahasend\Data\EmailMessage;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Send a basic plain-text email via the Ahasend API.
 */
class SendEmailRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(protected readonly EmailMessage $message) {}

    public function resolveEndpoint(): string
    {
        return '/emails/send';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $payload = [
            'from' => [
                'email' => $this->message->fromEmail,
                'name'  => $this->message->fromName,
            ],
            'to'      => $this->message->to,
            'subject' => $this->message->subject,
        ];

        if ($this->message->textContent !== null) {
            $payload['text'] = $this->message->textContent;
        }

        if (! empty($this->message->cc)) {
            $payload['cc'] = $this->message->cc;
        }

        if (! empty($this->message->bcc)) {
            $payload['bcc'] = $this->message->bcc;
        }

        if ($this->message->messageId !== null) {
            $payload['message_id'] = $this->message->messageId;
        }

        return $payload;
    }
}
