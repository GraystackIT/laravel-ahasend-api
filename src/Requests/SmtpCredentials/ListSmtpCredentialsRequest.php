<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\SmtpCredentials;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListSmtpCredentialsRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/smtp-credentials';
    }
}
