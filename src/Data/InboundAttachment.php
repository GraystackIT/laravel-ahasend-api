<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * A single attachment carried by an inbound routed message.
 *
 * Ahasend delivers attachments inline as base64 when the route has the
 * `attachments` option enabled. That includes conventional attachments, inline
 * MIME parts referenced by `cid:` in the HTML body, and filename-bearing parts
 * without a Content-Disposition header.
 */
final class InboundAttachment
{
    public function __construct(
        public readonly string $fileName,
        public readonly string $contentType,
        public readonly string $encodedContent,
        public readonly ?string $contentId = null,
        public readonly bool $inline = false,
        public readonly ?int $size = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $contentId = isset($data['content_id']) ? trim((string) $data['content_id'], '<>') : null;

        return new self(
            fileName:       (string) ($data['file_name'] ?? $data['filename'] ?? $data['name'] ?? ''),
            contentType:    (string) ($data['content_type'] ?? $data['contentType'] ?? 'application/octet-stream'),
            encodedContent: (string) ($data['data'] ?? $data['content'] ?? ''),
            contentId:      ($contentId === null || $contentId === '') ? null : $contentId,
            inline:         (bool) ($data['inline'] ?? ($contentId !== null && $contentId !== '')),
            size:           isset($data['size']) ? (int) $data['size'] : null,
        );
    }

    /**
     * The decoded attachment bytes.
     */
    public function contents(): string
    {
        $decoded = base64_decode($this->encodedContent, true);

        return $decoded === false ? '' : $decoded;
    }
}
