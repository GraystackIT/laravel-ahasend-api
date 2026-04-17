<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Messages;

use GraystackIT\Ahasend\Enums\MessageStatus;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListMessagesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int            $page = 1,
        private readonly int            $perPage = 25,
        private readonly ?MessageStatus $status = null,
        private readonly ?string        $from = null,
        private readonly ?string        $to = null,
        private readonly ?string        $email = null,
    ) {
        if ($this->page < 1) {
            throw new \InvalidArgumentException('Page must be at least 1.');
        }

        if ($this->perPage < 1 || $this->perPage > 100) {
            throw new \InvalidArgumentException('Per-page must be between 1 and 100.');
        }
    }

    public function resolveEndpoint(): string
    {
        return '/messages';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $query = [
            'page'     => $this->page,
            'per_page' => $this->perPage,
        ];

        if ($this->status !== null) {
            $query['status'] = $this->status->value;
        }

        if ($this->from !== null) {
            $query['from'] = $this->from;
        }

        if ($this->to !== null) {
            $query['to'] = $this->to;
        }

        if ($this->email !== null) {
            $query['email'] = $this->email;
        }

        return $query;
    }
}
