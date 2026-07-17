<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents a suppression record returned by the Ahasend API.
 */
final class Suppression
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $email,
        public readonly string  $expiresAt,
        public readonly ?string $domain = null,
        public readonly ?string $reason = null,
        public readonly ?string $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id:        (string) ($data['id'] ?? ''),
            email:     (string) ($data['email'] ?? ''),
            expiresAt: (string) ($data['expires_at'] ?? ''),
            domain:    isset($data['domain']) ? (string) $data['domain'] : null,
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
            'id'         => $this->id,
            'email'      => $this->email,
            'expires_at' => $this->expiresAt,
            'domain'     => $this->domain,
            'reason'     => $this->reason,
            'created_at' => $this->createdAt,
        ];
    }
}
