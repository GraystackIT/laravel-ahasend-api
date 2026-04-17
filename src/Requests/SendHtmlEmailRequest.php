<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests;

use GraystackIT\Ahasend\Data\EmailMessage;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class SendHtmlEmailRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(protected readonly EmailMessage $message) {}

    public function resolveEndpoint(): string
    {
        return '/messages';
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
            'recipients'   => $this->message->to,
            'subject'      => $this->message->subject,
            'html_content' => $this->message->htmlContent,
        ];

        if ($this->message->textContent !== null) {
            $payload['text_content'] = $this->message->textContent;
        }

        if (! empty($this->message->cc)) {
            $payload['cc'] = $this->message->cc;
        }

        if (! empty($this->message->bcc)) {
            $payload['bcc'] = $this->message->bcc;
        }

        return $payload;
    }
}
