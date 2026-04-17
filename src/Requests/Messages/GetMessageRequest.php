<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Messages;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetMessageRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $messageId)
    {
        if ($this->messageId === '') {
            throw new \InvalidArgumentException('Message ID must not be empty.');
        }
    }

    public function resolveEndpoint(): string
    {
        return "/messages/{$this->messageId}";
    }
}
