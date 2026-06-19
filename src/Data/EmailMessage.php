<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents an outgoing email message to be sent via Ahasend.
 */
final class EmailMessage
{
    /**
     * @param  array<int, array{email: string, name?: string}>  $to
     * @param  array<int, array{email: string, name?: string}>  $cc
     * @param  array<int, array{email: string, name?: string}>  $bcc
     * @param  array<int, array{name: string, content: string, mime_type: string}>  $attachments
     * @param  string[]|null  $tags
     * @param  array<string, mixed>|null  $tracking
     * @param  array<string, mixed>|null  $schedule
     * @param  array<string, mixed>|null  $retention
     * @param  array<string, mixed>|null  $substitutions
     */
    public function __construct(
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly array $to,
        public readonly string $subject,
        public readonly ?string $htmlContent = null,
        public readonly ?string $textContent = null,
        public readonly array $cc = [],
        public readonly array $bcc = [],
        public readonly array $attachments = [],
        public readonly ?string $messageId = null,
        public readonly ?array $tags = null,
        public readonly ?array $tracking = null,
        public readonly ?array $schedule = null,
        public readonly ?array $retention = null,
        public readonly ?array $substitutions = null,
        public readonly ?string $sandboxResult = null,
    ) {}

    /**
     * Build from a flat array (useful in Mailable trait and tests).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fromEmail:     $data['from_email'] ?? '',
            fromName:      $data['from_name'] ?? '',
            to:            $data['to'] ?? [],
            subject:       $data['subject'] ?? '',
            htmlContent:   $data['html_content'] ?? null,
            textContent:   $data['text_content'] ?? null,
            cc:            $data['cc'] ?? [],
            bcc:           $data['bcc'] ?? [],
            attachments:   $data['attachments'] ?? [],
            messageId:     $data['message_id'] ?? null,
            tags:          $data['tags'] ?? null,
            tracking:      $data['tracking'] ?? null,
            schedule:      $data['schedule'] ?? null,
            retention:     $data['retention'] ?? null,
            substitutions: $data['substitutions'] ?? null,
            sandboxResult: $data['sandbox_result'] ?? null,
        );
    }

    /**
     * Serialize to array for API payloads and DB storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from_email'    => $this->fromEmail,
            'from_name'     => $this->fromName,
            'to'            => $this->to,
            'subject'       => $this->subject,
            'html_content'  => $this->htmlContent,
            'text_content'  => $this->textContent,
            'cc'            => $this->cc,
            'bcc'           => $this->bcc,
            'attachments'   => $this->attachments,
            'message_id'    => $this->messageId,
            'tags'          => $this->tags,
            'tracking'      => $this->tracking,
            'schedule'      => $this->schedule,
            'retention'     => $this->retention,
            'substitutions' => $this->substitutions,
            'sandbox_result' => $this->sandboxResult,
        ];
    }
}
