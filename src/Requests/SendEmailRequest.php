<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests;

use GraystackIT\Ahasend\Data\EmailMessage;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SendEmailRequest extends Request implements HasBody
{
    use HasJsonBody;

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
            'text_content' => $this->message->textContent,
        ];

        return $this->appendOptionalFields($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function appendOptionalFields(array $payload): array
    {
        if ($this->message->tags !== null) {
            $payload['tags'] = $this->message->tags;
        }

        if ($this->message->tracking !== null) {
            $payload['tracking'] = $this->message->tracking;
        }

        if ($this->message->schedule !== null) {
            $payload['schedule'] = $this->message->schedule;
        }

        if ($this->message->retention !== null) {
            $payload['retention'] = $this->message->retention;
        }

        if ($this->message->substitutions !== null) {
            $payload['substitutions'] = $this->message->substitutions;
        }

        if ($this->message->sandboxResult !== null) {
            $payload['sandbox_result'] = $this->message->sandboxResult;
        }

        if ($this->message->replyTo !== null) {
            $payload['reply_to'] = $this->message->replyTo;
        }

        if ($this->message->headers !== null) {
            $payload['headers'] = $this->message->headers;
        }

        if ($this->message->ampContent !== null) {
            $payload['amp_content'] = $this->message->ampContent;
        }

        if ($this->message->sandbox !== null) {
            $payload['sandbox'] = $this->message->sandbox;
        }

        return $payload;
    }
}
