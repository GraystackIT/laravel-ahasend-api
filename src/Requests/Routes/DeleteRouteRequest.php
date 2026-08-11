<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Routes;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Deletes an inbound message route.
 */
class DeleteRouteRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly string $routeId) {}

    public function resolveEndpoint(): string
    {
        return '/routes/' . rawurlencode($this->routeId);
    }
}
