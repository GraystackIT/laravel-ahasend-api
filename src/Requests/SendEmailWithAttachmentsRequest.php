<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests;

use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class SendEmailWithAttachmentsRequest extends Request
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
            'recipients'  => $this->message->to,
            'subject'     => $this->message->subject,
            'attachments' => $this->buildAttachments(),
        ];

        if ($this->message->htmlContent !== null) {
            $payload['html_content'] = $this->message->htmlContent;
        }

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

    /**
     * @return array<int, array<string, string>>
     */
    private function buildAttachments(): array
    {
        $built = [];

        foreach ($this->message->attachments as $attachment) {
            if (isset($attachment['path'])) {
                $path = $attachment['path'];

                if (! file_exists($path) || ! is_readable($path)) {
                    throw AhasendException::make("Attachment file not found or unreadable: {$path}");
                }

                $content  = base64_encode((string) file_get_contents($path));
                $mimeType = $attachment['mime_type'] ?? mime_content_type($path) ?: 'application/octet-stream';
                $name     = $attachment['name'] ?? basename($path);
            } else {
                $rawContent = $attachment['content'] ?? '';
                $content    = base64_encode(base64_decode($rawContent, strict: true) !== false
                    ? base64_decode($rawContent)
                    : $rawContent);
                $mimeType = $attachment['mime_type'] ?? 'application/octet-stream';
                $name     = $attachment['name'] ?? 'attachment';
            }

            $built[] = [
                'name'      => $name,
                'content'   => $content,
                'mime_type' => $mimeType,
            ];
        }

        return $built;
    }
}
