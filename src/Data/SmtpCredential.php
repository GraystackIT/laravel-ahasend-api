<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents an SMTP credential returned by the Ahasend API.
 */
final class SmtpCredential
{
    /**
     * @param  string[]  $domains  Domains this credential is scoped to; empty when $scope is "global"
     */
    public function __construct(
        public readonly string  $id,
        public readonly string  $name,
        public readonly string  $username,
        public readonly bool    $sandbox,
        public readonly string  $scope,
        public readonly array   $domains = [],
        public readonly ?string $password = null,
        public readonly ?string $createdAt = null,
    ) {}

    /**
     * Build from a raw Ahasend API SMTP credential item.
     *
     * Ahasend's response does not include the SMTP host/port to connect to — those are fixed
     * per the SMTP relay docs, not per credential — so they are not modeled here.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id:        (string) ($data['id'] ?? ''),
            name:      (string) ($data['name'] ?? ''),
            username:  (string) ($data['username'] ?? ''),
            sandbox:   (bool) ($data['sandbox'] ?? false),
            scope:     (string) ($data['scope'] ?? 'global'),
            domains:   $data['domains'] ?? [],
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
            'sandbox'    => $this->sandbox,
            'scope'      => $this->scope,
            'domains'    => $this->domains,
            'password'   => $this->password,
            'created_at' => $this->createdAt,
        ];
    }
}
