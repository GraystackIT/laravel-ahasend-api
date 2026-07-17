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
     * @param  string[]  $tags
     */
    public function __construct(
        public readonly string        $id,
        public readonly string        $subject,
        public readonly string        $sender,
        public readonly string        $recipient,
        public readonly MessageStatus $status,
        public readonly array         $tags = [],
        public readonly ?string       $sentAt = null,
        public readonly ?string       $deliveredAt = null,
        public readonly ?string       $createdAt = null,
        public readonly ?string       $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id:          (string) ($data['id'] ?? $data['message_id'] ?? ''),
            subject:     (string) ($data['subject'] ?? ''),
            sender:      (string) ($data['sender'] ?? ''),
            recipient:   (string) ($data['recipient'] ?? ''),
            status:      MessageStatus::from($data['status'] ?? 'queued'),
            tags:        $data['tags'] ?? [],
            sentAt:      isset($data['sent_at']) ? (string) $data['sent_at'] : null,
            deliveredAt: isset($data['delivered_at']) ? (string) $data['delivered_at'] : null,
            createdAt:   isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt:   isset($data['updated_at']) ? (string) $data['updated_at'] : null,
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
            'sender'       => $this->sender,
            'recipient'    => $this->recipient,
            'status'       => $this->status->value,
            'tags'         => $this->tags,
            'sent_at'      => $this->sentAt,
            'delivered_at' => $this->deliveredAt,
            'created_at'   => $this->createdAt,
            'updated_at'   => $this->updatedAt,
        ];
    }
}
