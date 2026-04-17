<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\SmtpCredentials;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CreateSmtpCredentialRequest extends Request
{
    protected Method $method = Method::POST;

    /**
     * @param  array<int, string>  $domains  Required when $scope is "scoped"
     */
    public function __construct(
        private readonly string $name,
        private readonly string $scope = 'global',
        private readonly bool   $sandbox = false,
        private readonly array  $domains = [],
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('SMTP credential name must not be empty.');
        }

        if (! in_array($this->scope, ['global', 'scoped'], true)) {
            throw new \InvalidArgumentException('SMTP credential scope must be "global" or "scoped".');
        }

        if ($this->scope === 'scoped' && empty($this->domains)) {
            throw new \InvalidArgumentException('Domains are required when scope is "scoped".');
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
        $body = [
            'name'    => $this->name,
            'scope'   => $this->scope,
            'sandbox' => $this->sandbox,
        ];

        if (! empty($this->domains)) {
            $body['domains'] = $this->domains;
        }

        return $body;
    }
}
