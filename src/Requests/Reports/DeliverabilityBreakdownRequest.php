<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Reports;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeliverabilityBreakdownRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ?string $fromTime = null,
        private readonly ?string $toTime = null,
        private readonly ?string $senderDomain = null,
        private readonly ?string $recipientDomains = null,
        private readonly ?string $tags = null,
        private readonly ?string $groupBy = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/statistics/transactional/deliverability';
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

        if ($this->recipientDomains !== null) {
            $query['recipient_domains'] = $this->recipientDomains;
        }

        if ($this->tags !== null) {
            $query['tags'] = $this->tags;
        }

        if ($this->groupBy !== null) {
            $query['group_by'] = $this->groupBy;
        }

        return $query;
    }
}
