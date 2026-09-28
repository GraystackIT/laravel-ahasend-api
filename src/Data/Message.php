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
     * @param  array<int, array<string, mixed>>  $deliveryAttempts  Raw delivery attempt log entries, passed through as-is
     */
    public function __construct(
        public readonly string        $id,
        public readonly string        $subject,
        public readonly string        $sender,
        public readonly string        $recipient,
        public readonly MessageStatus $status,
        /**
         * The status string exactly as Ahasend returned it. Ahasend documents `status` as an
         * open set of values, so this is the only reliable way to read a status that doesn't
         * map to a {@see MessageStatus} case (surfaced as {@see MessageStatus::Unknown}).
         */
        public readonly string        $rawStatus,
        public readonly array         $tags = [],
        public readonly ?string       $sentAt = null,
        public readonly ?string       $deliveredAt = null,
        public readonly ?string       $createdAt = null,
        public readonly ?string       $updatedAt = null,
        public readonly ?string       $direction = null,
        public readonly int           $numAttempts = 0,
        public readonly array         $deliveryAttempts = [],
        public readonly bool          $isBounceNotification = false,
        public readonly ?string       $bounceClassification = null,
        public readonly ?int          $referenceMessageId = null,
        public readonly int           $clickCount = 0,
        public readonly int           $openCount = 0,
        public readonly ?string       $content = null,
        public readonly ?string       $retainUntil = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawStatus = (string) ($data['status'] ?? 'queued');

        return new self(
            id:                    (string) ($data['id'] ?? $data['message_id'] ?? ''),
            subject:               (string) ($data['subject'] ?? ''),
            sender:                (string) ($data['sender'] ?? ''),
            recipient:             (string) ($data['recipient'] ?? ''),
            status:                MessageStatus::tryFrom($rawStatus) ?? MessageStatus::Unknown,
            rawStatus:             $rawStatus,
            tags:                  $data['tags'] ?? [],
            sentAt:                isset($data['sent_at']) ? (string) $data['sent_at'] : null,
            deliveredAt:           isset($data['delivered_at']) ? (string) $data['delivered_at'] : null,
            createdAt:             isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt:             isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            direction:             isset($data['direction']) ? (string) $data['direction'] : null,
            numAttempts:           (int) ($data['num_attempts'] ?? 0),
            deliveryAttempts:      $data['delivery_attempts'] ?? [],
            isBounceNotification:  (bool) ($data['is_bounce_notification'] ?? false),
            bounceClassification:  isset($data['bounce_classification']) ? (string) $data['bounce_classification'] : null,
            referenceMessageId:    isset($data['reference_message_id']) ? (int) $data['reference_message_id'] : null,
            clickCount:            (int) ($data['click_count'] ?? 0),
            openCount:             (int) ($data['open_count'] ?? 0),
            content:               isset($data['content']) ? (string) $data['content'] : null,
            retainUntil:           isset($data['retain_until']) ? (string) $data['retain_until'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                     => $this->id,
            'subject'                => $this->subject,
            'sender'                 => $this->sender,
            'recipient'              => $this->recipient,
            'status'                 => $this->status->value,
            'raw_status'             => $this->rawStatus,
            'tags'                   => $this->tags,
            'sent_at'                => $this->sentAt,
            'delivered_at'           => $this->deliveredAt,
            'created_at'             => $this->createdAt,
            'updated_at'             => $this->updatedAt,
            'direction'              => $this->direction,
            'num_attempts'           => $this->numAttempts,
            'delivery_attempts'      => $this->deliveryAttempts,
            'is_bounce_notification' => $this->isBounceNotification,
            'bounce_classification'  => $this->bounceClassification,
            'reference_message_id'   => $this->referenceMessageId,
            'click_count'            => $this->clickCount,
            'open_count'             => $this->openCount,
            'content'                => $this->content,
            'retain_until'           => $this->retainUntil,
        ];
    }
}
