<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Suppressions;

use GraystackIT\Ahasend\Enums\SuppressionType;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class CreateSuppressionRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        private readonly string          $email,
        private readonly SuppressionType $type = SuppressionType::Manual,
        private readonly ?string         $reason = null,
    ) {
        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address: {$this->email}");
        }
    }

    public function resolveEndpoint(): string
    {
        return '/suppressions';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'email' => $this->email,
            'type'  => $this->type->value,
        ];

        if ($this->reason !== null) {
            $body['reason'] = $this->reason;
        }

        return $body;
    }
}
