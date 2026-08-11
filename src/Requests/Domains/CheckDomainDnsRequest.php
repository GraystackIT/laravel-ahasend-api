<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Domains;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Triggers a DNS verification run for a domain.
 *
 * Ahasend has no "domain verified" webhook — only `domain.dns_error` — so this
 * request is the only way a domain moves to verified.
 */
class CheckDomainDnsRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(private readonly string $domain) {}

    public function resolveEndpoint(): string
    {
        return '/domains/' . rawurlencode($this->domain) . '/check-dns';
    }
}
