<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Suppressions;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteSuppressionRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $email,
        private readonly ?string $domain = null,
    ) {
        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address: {$this->email}");
        }
    }

    public function resolveEndpoint(): string
    {
        return '/suppressions';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = ['email' => $this->email];

        if ($this->domain !== null) {
            $query['domain'] = $this->domain;
        }

        return $query;
    }
}
