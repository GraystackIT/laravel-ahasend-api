<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Suppressions;

use GraystackIT\Ahasend\Enums\SuppressionType;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListSuppressionsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int               $page = 1,
        private readonly int               $perPage = 25,
        private readonly ?SuppressionType  $type = null,
        private readonly ?string           $email = null,
    ) {
        if ($this->page < 1) {
            throw new \InvalidArgumentException('Page must be at least 1.');
        }

        if ($this->perPage < 1 || $this->perPage > 100) {
            throw new \InvalidArgumentException('Per-page must be between 1 and 100.');
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
        $query = [
            'page'     => $this->page,
            'per_page' => $this->perPage,
        ];

        if ($this->type !== null) {
            $query['type'] = $this->type->value;
        }

        if ($this->email !== null) {
            $query['email'] = $this->email;
        }

        return $query;
    }
}
