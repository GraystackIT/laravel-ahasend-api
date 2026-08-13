<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Domains;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Fetches a single domain including its current DNS record state.
 */
class GetDomainRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $domain) {}

    public function resolveEndpoint(): string
    {
        return '/domains/' . rawurlencode($this->domain);
    }
}
