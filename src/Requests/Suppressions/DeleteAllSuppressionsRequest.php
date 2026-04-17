<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Suppressions;

use GraystackIT\Ahasend\Enums\SuppressionType;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteAllSuppressionsRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly ?SuppressionType $type = null) {}

    public function resolveEndpoint(): string
    {
        return '/suppressions';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        if ($this->type !== null) {
            return ['type' => $this->type->value];
        }

        return [];
    }
}
