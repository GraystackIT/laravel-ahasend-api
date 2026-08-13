<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Domains;

use GraystackIT\Ahasend\Traits\HasIdempotencyKey;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Updates a domain's subdomains, DKIM rotation interval or DSN recipient.
 */
class UpdateDomainRequest extends Request implements HasBody
{
    use HasIdempotencyKey;
    use HasJsonBody;

    protected Method $method = Method::PUT;

    /**
     * @param  array<string, mixed>  $attributes  API field names, e.g. ["dsn_recipient" => "…"]
     *
     * @throws \InvalidArgumentException when no attribute is given
     */
    public function __construct(
        private readonly string $domain,
        private readonly array $attributes,
    ) {
        if ($this->attributes === []) {
            throw new \InvalidArgumentException('At least one attribute must be given to update a domain.');
        }
    }

    public function resolveEndpoint(): string
    {
        return '/domains/' . rawurlencode($this->domain);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->attributes;
    }
}
