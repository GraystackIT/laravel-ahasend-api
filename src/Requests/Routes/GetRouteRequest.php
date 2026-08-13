<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Routes;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Fetches a single inbound message route.
 */
class GetRouteRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $routeId) {}

    public function resolveEndpoint(): string
    {
        return '/routes/' . rawurlencode($this->routeId);
    }
}
