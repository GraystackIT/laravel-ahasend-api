<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

use GraystackIT\Ahasend\Enums\SuppressionType;

/**
 * Represents a suppression record returned by the Ahasend API.
 */
final class Suppression
{
    public function __construct(
        public readonly string          $email,
        public readonly SuppressionType $type,
        public readonly ?string         $reason = null,
        public readonly ?string         $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email:     (string) ($data['email'] ?? ''),
            type:      SuppressionType::from($data['type'] ?? 'manual'),
            reason:    isset($data['reason']) ? (string) $data['reason'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'email'      => $this->email,
            'type'       => $this->type->value,
            'reason'     => $this->reason,
            'created_at' => $this->createdAt,
        ];
    }
}
