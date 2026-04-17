<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\SmtpCredentials;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CreateSmtpCredentialRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(private readonly string $name)
    {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('SMTP credential name must not be empty.');
        }
    }

    public function resolveEndpoint(): string
    {
        return '/smtp-credentials';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
