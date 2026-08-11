<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Domains;

use GraystackIT\Ahasend\Traits\HasIdempotencyKey;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Registers a new sending/receiving domain on the Ahasend account.
 */
class CreateDomainRequest extends Request implements HasBody
{
    use HasIdempotencyKey;
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @throws \InvalidArgumentException when the domain is not a valid hostname
     */
    public function __construct(private readonly string $domain)
    {
        if (! self::isValidDomain($this->domain)) {
            throw new \InvalidArgumentException("Invalid domain name: {$this->domain}");
        }
    }

    public function resolveEndpoint(): string
    {
        return '/domains';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return ['domain' => $this->domain];
    }

    /**
     * Whether the given string is a syntactically usable domain name.
     */
    public static function isValidDomain(string $domain): bool
    {
        $domain = trim($domain);

        if ($domain === '' || strlen($domain) > 253 || ! str_contains($domain, '.')) {
            return false;
        }

        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
