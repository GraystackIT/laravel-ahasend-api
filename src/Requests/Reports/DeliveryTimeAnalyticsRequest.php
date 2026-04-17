<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Reports;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeliveryTimeAnalyticsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ?string $fromTime = null,
        private readonly ?string $toTime = null,
        private readonly ?string $senderDomain = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/reports/delivery-time';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->fromTime !== null) {
            $query['from_time'] = $this->fromTime;
        }

        if ($this->toTime !== null) {
            $query['to_time'] = $this->toTime;
        }

        if ($this->senderDomain !== null) {
            $query['sender_domain'] = $this->senderDomain;
        }

        return $query;
    }
}
