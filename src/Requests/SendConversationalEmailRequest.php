<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests;

use GraystackIT\Ahasend\Data\EmailMessage;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class SendConversationalEmailRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(protected readonly EmailMessage $message) {}

    public function resolveEndpoint(): string
    {
        return '/messages/conversation';
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
            'recipients' => $this->message->to,
            'subject'    => $this->message->subject,
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

        if (! empty($this->message->attachments)) {
            $payload['attachments'] = $this->buildAttachments();
        }

        return $this->appendOptionalFields($payload);
    }

    /**
     * Appends optional send fields supported by the conversational endpoint.
     * Note: substitutions are intentionally excluded — the conversation endpoint
     * does not support template variable substitution.
     *
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

        if ($this->message->sandboxResult !== null) {
            $payload['sandbox_result'] = $this->message->sandboxResult;
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
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

                $content     = base64_encode((string) file_get_contents($path));
                $contentType = $attachment['mime_type'] ?? mime_content_type($path) ?: 'application/octet-stream';
                $fileName    = $attachment['name'] ?? basename($path);
            } else {
                $rawContent  = $attachment['content'] ?? '';
                $content     = base64_encode(base64_decode($rawContent, strict: true) !== false
                    ? base64_decode($rawContent)
                    : $rawContent);
                $contentType = $attachment['mime_type'] ?? 'application/octet-stream';
                $fileName    = $attachment['name'] ?? 'attachment';
            }

            $built[] = [
                'file_name'    => $fileName,
                'data'         => $content,
                'content_type' => $contentType,
                'base64'       => true,
            ];
        }

        return $built;
    }
}
