<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Concerns;

use GraystackIT\Ahasend\Exceptions\AhasendException;

/**
 * Turns the attachments of an {@see \GraystackIT\Ahasend\Data\EmailMessage}
 * into the parts the send endpoints expect.
 *
 * Shared by the two requests that carry files, because the two had drifted into
 * the same code twice and a fix to one was a fix to only one.
 *
 * Bytes may arrive either way: as `content` already in hand, or as a `path` to
 * read. Both end up base64-encoded, because the API takes no other form.
 *
 * @property-read \GraystackIT\Ahasend\Data\EmailMessage $message
 */
trait BuildsAttachments
{
    /**
     * The message's attachments as API parts.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws AhasendException When an attachment names a file that cannot be read.
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

            $part = [
                'file_name'    => $fileName,
                'data'         => $content,
                'content_type' => $contentType,
                'base64'       => true,
            ];

            $contentId   = $this->contentIdOf($attachment);
            $disposition = trim((string) ($attachment['content_disposition'] ?? ''));

            if ($contentId !== null) {
                $part['content_id'] = $contentId;
            }

            // Inline by default once there is a content id — that is what the id is for — but
            // a disposition the caller states is theirs to choose.
            if ($disposition !== '') {
                $part['content_disposition'] = $disposition;
            } elseif ($contentId !== null) {
                $part['content_disposition'] = 'inline';
            }

            $built[] = $part;
        }

        return $built;
    }

    /**
     * The content id of one attachment, in the form the API asks for.
     *
     * Angle brackets are not decoration here: they are what tells the provider
     * to place the file inline, where the HTML body can reach it as
     * `cid:{id}`. Sent without them, the very same field delivers the file as
     * an ordinary download instead — so a caller who supplies a bare id gets it
     * wrapped rather than silently getting an attachment.
     *
     * @param  array<string, mixed>  $attachment  One entry of the message's attachment list.
     */
    private function contentIdOf(array $attachment): ?string
    {
        $contentId = trim((string) ($attachment['content_id'] ?? ''), " \t\n\r\0\x0B<>");

        return $contentId === '' ? null : '<'.$contentId.'>';
    }
}
