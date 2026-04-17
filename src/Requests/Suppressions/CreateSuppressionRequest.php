<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Suppressions;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CreateSuppressionRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        private readonly string  $email,
        private readonly string  $expiresAt,
        private readonly ?string $reason = null,
        private readonly ?string $domain = null,
    ) {
        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address: {$this->email}");
        }

        if (trim($this->expiresAt) === '') {
            throw new \InvalidArgumentException('expires_at must not be empty.');
        }
    }

    public function resolveEndpoint(): string
    {
        return '/suppressions';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'email'      => $this->email,
            'expires_at' => $this->expiresAt,
        ];

        if ($this->reason !== null) {
            $body['reason'] = $this->reason;
        }

        if ($this->domain !== null) {
            $body['domain'] = $this->domain;
        }

        return $body;
    }
}
