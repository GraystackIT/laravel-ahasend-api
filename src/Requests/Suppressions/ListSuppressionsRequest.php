<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Suppressions;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListSuppressionsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ?int    $limit = null,
        private readonly ?string $after = null,
        private readonly ?string $before = null,
        private readonly ?string $domain = null,
        private readonly ?string $email = null,
        private readonly ?string $fromTime = null,
        private readonly ?string $toTime = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/suppressions';
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

        if ($this->domain !== null) {
            $query['domain'] = $this->domain;
        }

        if ($this->email !== null) {
            $query['email'] = $this->email;
        }

        if ($this->fromTime !== null) {
            $query['from_time'] = $this->fromTime;
        }

        if ($this->toTime !== null) {
            $query['to_time'] = $this->toTime;
        }

        return $query;
    }
}
