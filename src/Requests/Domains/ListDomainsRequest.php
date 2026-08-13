<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Domains;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Lists the account's domains with cursor-based pagination.
 */
class ListDomainsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ?int $limit = null,
        private readonly ?string $after = null,
        private readonly ?string $before = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/domains';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return array_filter([
            'limit'  => $this->limit,
            'after'  => $this->after,
            'before' => $this->before,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
