<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Suppressions;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteAllSuppressionsRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly ?string $domain = null) {}

    public function resolveEndpoint(): string
    {
        return '/suppressions/all';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->domain !== null ? ['domain' => $this->domain] : [];
    }
}
