<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * A normalised inbound message delivered by an Ahasend route.
 *
 * Consumers work against this shape rather than the raw webhook array, so a
 * change on Ahasend's side is absorbed here instead of rippling through the
 * application. The raw payload stays available for anything not modelled yet.
 */
final class InboundMessage
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<InboundAttachment>  $attachments
     * @param  array<string, mixed>  $headers
     * @param  list<string>  $references
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $from,
        public readonly array $to,
        /**
         * The address this delivery was routed to.
         *
         * Ahasend puts it in the payload's top-level `to`, which is **not** the
         * `To:` header — that one lives in {@see $headers}. The difference is
         * load-bearing: a BCC'd address appears here but in no header, and with
         * one request per recipient each delivery names its own recipient, so
         * the same mail sent to two of your domains arrives twice with a
         * different value here each time.
         */
        public readonly ?string $recipient = null,
        public readonly string $subject = '',
        public readonly ?string $htmlBody = null,
        public readonly ?string $plainBody = null,
        public readonly ?string $replyPlainBody = null,
        public readonly array $cc = [],
        public readonly ?string $replyTo = null,
        public readonly ?string $inReplyTo = null,
        public readonly array $references = [],
        public readonly array $attachments = [],
        public readonly array $headers = [],
        public readonly ?float $spamScore = null,
        public readonly bool $bounce = false,
        public readonly ?string $autoSubmitted = null,
        public readonly ?int $size = null,
        public readonly ?string $date = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Build from the `data` object of a `message.routing` webhook payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId:      (string) ($data['message_id'] ?? $data['id'] ?? ''),
            from:           (string) ($data['from'] ?? ''),
            to:             self::addresses($data['to'] ?? null),
            // Verified against real payloads: the routed recipient arrives as
            // the top-level `to`, and Ahasend sends no `recipient` key here.
            recipient:      self::singleAddress($data['recipient'] ?? $data['to'] ?? null),
            subject:        (string) ($data['subject'] ?? ''),
            htmlBody:       isset($data['html_body']) ? (string) $data['html_body'] : null,
            plainBody:      isset($data['plain_body']) ? (string) $data['plain_body'] : null,
            replyPlainBody: isset($data['reply_from_plain_body']) ? (string) $data['reply_from_plain_body'] : null,
            cc:             self::addresses($data['cc'] ?? null),
            replyTo:        isset($data['reply_to']) ? (string) $data['reply_to'] : null,
            inReplyTo:      self::normaliseMessageId($data['in_reply_to'] ?? null),
            references:     self::referenceList($data['references'] ?? null),
            attachments:    self::attachmentList($data['attachments'] ?? null),
            headers:        is_array($data['headers'] ?? null) ? $data['headers'] : [],
            spamScore:      isset($data['spam_score']) ? (float) $data['spam_score'] : null,
            bounce:         (bool) ($data['bounce'] ?? false),
            autoSubmitted:  isset($data['auto_submitted']) ? (string) $data['auto_submitted'] : null,
            size:           isset($data['size']) ? (int) $data['size'] : null,
            date:           isset($data['date']) ? (string) $data['date'] : null,
            raw:            $data,
        );
    }

    /**
     * The best available text body: the reply-stripped one when the route
     * produced it, otherwise the full plain body.
     */
    public function text(): ?string
    {
        return $this->replyPlainBody ?? $this->plainBody;
    }

    /**
     * Whether this message announces itself as machine-generated.
     *
     * Auto-replies and bulk mail must never trigger another auto-reply.
     */
    public function isAutomated(): bool
    {
        if ($this->autoSubmitted !== null && strtolower($this->autoSubmitted) !== 'no') {
            return true;
        }

        $precedence = strtolower((string) ($this->header('Precedence') ?? ''));

        if (in_array($precedence, ['bulk', 'list', 'junk'], true)) {
            return true;
        }

        return $this->header('List-Id') !== null
            || $this->header('X-Auto-Response-Suppress') !== null;
    }

    /**
     * Look a raw header up case-insensitively.
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return null;
    }

    /**
     * Message ids this mail claims to answer, most specific first.
     *
     * This is what threading keys off: `In-Reply-To` names the direct parent,
     * `References` carries the chain, newest last — so it is walked backwards.
     *
     * @return list<string>
     */
    public function parentMessageIds(): array
    {
        $ids = [];

        if ($this->inReplyTo !== null) {
            $ids[] = $this->inReplyTo;
        }

        foreach (array_reverse($this->references) as $reference) {
            $ids[] = $reference;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Attachments that are referenced from the HTML body via `cid:`.
     *
     * @return list<InboundAttachment>
     */
    public function inlineAttachments(): array
    {
        return array_values(array_filter(
            $this->attachments,
            static fn (InboundAttachment $attachment): bool => $attachment->inline,
        ));
    }

    /**
     * Every address this mail was delivered to, most authoritative first.
     *
     * The routed recipient leads, because it is the only one guaranteed to be
     * the address the route matched — `to`/`cc` are headers and omit BCC.
     *
     * @return list<string>
     */
    public function deliveryAddresses(): array
    {
        $addresses = $this->recipient !== null ? [$this->recipient] : [];

        return array_values(array_unique([...$addresses, ...$this->to, ...$this->cc]));
    }

    /**
     * Normalise a single address that may arrive bare or RFC 5322 formatted.
     */
    private static function singleAddress(mixed $value): ?string
    {
        $addresses = self::addresses($value);

        return $addresses[0] ?? null;
    }

    /**
     * Normalise an address list that may arrive as a string or an array.
     *
     * @return list<string>
     */
    private static function addresses(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $addresses = [];

        foreach ($value as $entry) {
            $address = is_array($entry)
                ? (string) ($entry['email'] ?? $entry['address'] ?? '')
                : self::bareAddress((string) $entry);

            if ($address !== '') {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Strip an RFC 5322 display name, leaving just the address.
     *
     * `"Acme Support" <support@acme.at>` becomes `support@acme.at`, so callers
     * can read the domain without parsing the header form themselves.
     */
    private static function bareAddress(string $entry): string
    {
        $entry = trim($entry);

        if (preg_match('/<([^<>]+)>\s*$/', $entry, $matches) === 1) {
            return trim($matches[1]);
        }

        return $entry;
    }

    /**
     * @return list<string>
     */
    private static function referenceList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s+/', trim($value)) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $references = [];

        foreach ($value as $reference) {
            $normalised = self::normaliseMessageId($reference);

            if ($normalised !== null) {
                $references[] = $normalised;
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * Strip the angle brackets an RFC 5322 message id is wrapped in.
     */
    private static function normaliseMessageId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $id = trim(trim($value), '<>');

        return $id !== '' ? $id : null;
    }

    /**
     * @return list<InboundAttachment>
     */
    private static function attachmentList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): InboundAttachment => InboundAttachment::fromArray($item),
            array_filter($value, 'is_array'),
        ));
    }
}
