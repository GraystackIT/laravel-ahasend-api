<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

use GraystackIT\Ahasend\Enums\MessageStatus;

/**
 * Represents a message record returned by the Ahasend API.
 */
final class Message
{
    /**
     * @param  array<int, array{email: string, name?: string}>  $to
     * @param  array<int, array{email: string, name?: string}>  $cc
     */
    public function __construct(
        public readonly string        $id,
        public readonly string        $subject,
        public readonly string        $fromEmail,
        public readonly string        $fromName,
        public readonly array         $to,
        public readonly MessageStatus $status,
        public readonly array         $cc = [],
        public readonly ?string       $scheduledAt = null,
        public readonly ?string       $sentAt = null,
        public readonly ?string       $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $from = $data['from'] ?? [];

        return new self(
            id:          (string) ($data['id'] ?? $data['message_id'] ?? ''),
            subject:     (string) ($data['subject'] ?? ''),
            fromEmail:   (string) ($from['email'] ?? ''),
            fromName:    (string) ($from['name'] ?? ''),
            to:          $data['to'] ?? [],
            status:      MessageStatus::from($data['status'] ?? 'sent'),
            cc:          $data['cc'] ?? [],
            scheduledAt: isset($data['scheduled_at']) ? (string) $data['scheduled_at'] : null,
            sentAt:      isset($data['sent_at']) ? (string) $data['sent_at'] : null,
            createdAt:   isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'subject'      => $this->subject,
            'from_email'   => $this->fromEmail,
            'from_name'    => $this->fromName,
            'to'           => $this->to,
            'cc'           => $this->cc,
            'status'       => $this->status->value,
            'scheduled_at' => $this->scheduledAt,
            'sent_at'      => $this->sentAt,
            'created_at'   => $this->createdAt,
        ];
    }
}
