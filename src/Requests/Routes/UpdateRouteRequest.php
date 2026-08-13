<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Routes;

use GraystackIT\Ahasend\Traits\HasIdempotencyKey;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Updates an inbound message route.
 */
class UpdateRouteRequest extends Request implements HasBody
{
    use HasIdempotencyKey;
    use HasJsonBody;

    protected Method $method = Method::PUT;

    /**
     * @param  array<string, mixed>  $attributes  API field names, e.g. ["enabled" => false]
     *
     * @throws \InvalidArgumentException when no attribute is given
     */
    public function __construct(
        private readonly string $routeId,
        private readonly array $attributes,
    ) {
        if ($this->attributes === []) {
            throw new \InvalidArgumentException('At least one attribute must be given to update a route.');
        }
    }

    public function resolveEndpoint(): string
    {
        return '/routes/' . rawurlencode($this->routeId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->attributes;
    }
}
