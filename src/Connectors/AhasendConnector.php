<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Connectors;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class AhasendConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $accountId,
        private readonly string $baseUrl = 'https://api.ahasend.com/v2',
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/accounts/' . $this->accountId;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'X-Api-Key'    => $this->apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
