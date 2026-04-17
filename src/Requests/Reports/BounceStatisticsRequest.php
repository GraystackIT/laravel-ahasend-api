<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Reports;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class BounceStatisticsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly ?string $domain = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/reports/bounces';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->from !== null) {
            $query['from'] = $this->from;
        }

        if ($this->to !== null) {
            $query['to'] = $this->to;
        }

        if ($this->domain !== null) {
            $query['domain'] = $this->domain;
        }

        return $query;
    }
}
