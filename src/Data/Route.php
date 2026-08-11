<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents an inbound message route returned by the Ahasend API.
 *
 * A route matches inbound mail by its `recipient` pattern and forwards it to
 * `url`. Ahasend derives the owning domain from that pattern, so a route can
 * only be created for a domain that is already verified on the account.
 */
final class Route
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly string $recipient,
        public readonly bool $attachments = false,
        public readonly bool $headers = false,
        public readonly bool $groupByMessageId = false,
        public readonly bool $stripReplies = false,
        public readonly bool $enabled = true,
        /** Only ever returned by the create endpoint — persist it immediately. */
        public readonly ?string $secret = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id:                (string) ($data['id'] ?? ''),
            name:              (string) ($data['name'] ?? ''),
            url:               (string) ($data['url'] ?? ''),
            recipient:         (string) ($data['recipient'] ?? ''),
            attachments:       (bool) ($data['attachments'] ?? false),
            headers:           (bool) ($data['headers'] ?? false),
            groupByMessageId:  (bool) ($data['group_by_message_id'] ?? false),
            stripReplies:      (bool) ($data['strip_replies'] ?? false),
            enabled:           (bool) ($data['enabled'] ?? true),
            secret:            isset($data['secret']) ? (string) $data['secret'] : null,
            createdAt:         isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt:         isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * The domain part of the recipient pattern, e.g. "acme.at" for "*@acme.at".
     */
    public function domain(): ?string
    {
        $position = strrpos($this->recipient, '@');

        if ($position === false) {
            return null;
        }

        $domain = substr($this->recipient, $position + 1);

        return $domain !== '' ? $domain : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'url'                 => $this->url,
            'recipient'           => $this->recipient,
            'attachments'         => $this->attachments,
            'headers'             => $this->headers,
            'group_by_message_id' => $this->groupByMessageId,
            'strip_replies'       => $this->stripReplies,
            'enabled'             => $this->enabled,
            'created_at'          => $this->createdAt,
            'updated_at'          => $this->updatedAt,
        ];
    }
}
