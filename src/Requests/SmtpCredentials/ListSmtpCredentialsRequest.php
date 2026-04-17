<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\SmtpCredentials;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListSmtpCredentialsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ?int    $limit = null,
        private readonly ?string $after = null,
        private readonly ?string $before = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/smtp-credentials';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->limit !== null) {
            $query['limit'] = $this->limit;
        }

        if ($this->after !== null) {
            $query['after'] = $this->after;
        }

        if ($this->before !== null) {
            $query['before'] = $this->before;
        }

        return $query;
    }
}
