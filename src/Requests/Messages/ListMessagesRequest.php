<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Messages;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListMessagesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ?int    $limit = null,
        private readonly ?string $after = null,
        private readonly ?string $before = null,
        private readonly ?string $status = null,
        private readonly ?string $sender = null,
        private readonly ?string $recipient = null,
        private readonly ?string $tags = null,
        private readonly ?string $fromTime = null,
        private readonly ?string $toTime = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/messages';
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

        if ($this->status !== null) {
            $query['status'] = $this->status;
        }

        if ($this->sender !== null) {
            $query['sender'] = $this->sender;
        }

        if ($this->recipient !== null) {
            $query['recipient'] = $this->recipient;
        }

        if ($this->tags !== null) {
            $query['tags'] = $this->tags;
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
