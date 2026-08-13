<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

use GraystackIT\Ahasend\Enums\DnsRecordType;

/**
 * A single DNS record Ahasend expects to find for a domain.
 */
final class DnsRecord
{
    public function __construct(
        public readonly ?DnsRecordType $type,
        public readonly string $host,
        public readonly string $content,
        public readonly bool $required = true,
        public readonly bool $propagated = false,
        public readonly ?string $label = null,
        public readonly ?string $rawType = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawType = isset($data['type']) ? (string) $data['type'] : '';

        return new self(
            type:       DnsRecordType::fromApi($rawType),
            host:       (string) ($data['host'] ?? ''),
            content:    (string) ($data['content'] ?? ''),
            required:   (bool) ($data['required'] ?? true),
            propagated: (bool) ($data['propagated'] ?? false),
            label:      isset($data['label']) ? (string) $data['label'] : null,
            rawType:    $rawType !== '' ? $rawType : null,
        );
    }

    /**
     * Whether this record still needs attention before the domain can be used.
     */
    public function isOutstanding(): bool
    {
        return $this->required && ! $this->propagated;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type'       => $this->type?->value ?? $this->rawType,
            'host'       => $this->host,
            'content'    => $this->content,
            'required'   => $this->required,
            'propagated' => $this->propagated,
            'label'      => $this->label,
        ];
    }
}
