<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents an SMTP credential returned by the Ahasend API.
 */
final class SmtpCredential
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $name,
        public readonly string  $username,
        public readonly string  $host,
        public readonly int     $port,
        public readonly ?string $password = null,
        public readonly ?string $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id:        (string) ($data['id'] ?? ''),
            name:      (string) ($data['name'] ?? ''),
            username:  (string) ($data['username'] ?? ''),
            host:      (string) ($data['host'] ?? 'smtp.ahasend.com'),
            port:      (int) ($data['port'] ?? 587),
            password:  isset($data['password']) ? (string) $data['password'] : null,
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
            'name'       => $this->name,
            'username'   => $this->username,
            'host'       => $this->host,
            'port'       => $this->port,
            'password'   => $this->password,
            'created_at' => $this->createdAt,
        ];
    }
}
