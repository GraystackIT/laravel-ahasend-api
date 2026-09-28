<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests;

use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Sends via the plain `/messages` endpoint, which does not accept `cc`/`bcc` at all —
 * {@see \GraystackIT\Ahasend\AhasendService::resolveRequest()} always routes a message with
 * cc/bcc through {@see SendConversationalEmailRequest} instead, before this class is reached.
 */
class SendEmailWithAttachmentsRequest extends Request implements HasBody
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

        if ($this->message->templateId !== null) {
            $payload['template_id'] = $this->message->templateId;
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

            $built[] = array_filter([
                'file_name'           => $name,
                'data'                => $content,
                'content_type'        => $mimeType,
                'base64'              => true,
                // Set content_id (e.g. "<image1@example.com>") to reference the attachment
                // inline via cid:image1@example.com in html_content.
                'content_id'          => $attachment['content_id'] ?? null,
                'content_disposition' => $attachment['content_disposition'] ?? null,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $built;
    }
}
