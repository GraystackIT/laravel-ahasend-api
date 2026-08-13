<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * A single attachment carried by an inbound routed message.
 *
 * Ahasend delivers attachments as base64 inside the payload when the route has
 * the `attachments` option enabled. That covers conventional attachments as
 * well as inline MIME parts referenced by `cid:` in the HTML body; the two are
 * told apart by `disposition`.
 *
 * Field names verified against real payloads: `filename`, `content_type`,
 * `content_id`, `disposition`, `data`.
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
        $contentId = ($contentId === null || $contentId === '') ? null : $contentId;

        return new self(
            fileName:       (string) ($data['file_name'] ?? $data['filename'] ?? $data['name'] ?? ''),
            contentType:    (string) ($data['content_type'] ?? $data['contentType'] ?? 'application/octet-stream'),
            encodedContent: (string) ($data['data'] ?? $data['content'] ?? ''),
            contentId:      $contentId,
            // Ahasend states this as `disposition` ("inline" / "attachment"),
            // which is authoritative when present. A content id alone is only a
            // hint — a client may set one on a plain attachment — so it decides
            // just for payload shapes that carry no disposition at all.
            inline:         isset($data['disposition'])
                ? strtolower((string) $data['disposition']) === 'inline'
                : (bool) ($data['inline'] ?? $contentId !== null),
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
