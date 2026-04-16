<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests;

use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Send an email with one or more file attachments via the Ahasend API.
 *
 * Attachments can be provided either as file paths (resolved to base64) or
 * as raw binary / base64-encoded content strings.
 */
class SendEmailWithAttachmentsRequest extends Request
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
            'to'          => $this->message->to,
            'subject'     => $this->message->subject,
            'attachments' => $this->buildAttachments(),
        ];

        if ($this->message->htmlContent !== null) {
            $payload['html'] = $this->message->htmlContent;
        }

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

    /**
     * Convert attachments to the Ahasend API format.
     * Each attachment is: {name, content (base64), mime_type}.
     * If a file path is given under `path`, content is read and base64-encoded.
     *
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
                // Raw content — caller is responsible for supplying base64-encoded data
                // or binary string; we base64-encode binary strings automatically.
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
