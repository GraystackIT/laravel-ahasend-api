<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Domains;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Removes a domain from the Ahasend account.
 */
class DeleteDomainRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly string $domain) {}

    public function resolveEndpoint(): string
    {
        return '/domains/' . rawurlencode($this->domain);
    }
}
