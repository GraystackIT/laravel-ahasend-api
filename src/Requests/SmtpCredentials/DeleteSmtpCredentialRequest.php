<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\SmtpCredentials;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteSmtpCredentialRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly string $credentialId)
    {
        if ($this->credentialId === '') {
            throw new \InvalidArgumentException('Credential ID must not be empty.');
        }
    }

    public function resolveEndpoint(): string
    {
        return "/smtp-credentials/{$this->credentialId}";
    }
}
