<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Requests\Routes;

use GraystackIT\Ahasend\Traits\HasIdempotencyKey;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Creates an inbound message route.
 *
 * The response is the only place the route's signing secret is ever returned,
 * so callers must persist it right away.
 */
class CreateRouteRequest extends Request implements HasBody
{
    use HasIdempotencyKey;
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  string  $recipient  Match pattern, e.g. "*@acme.at". Ahasend derives
     *                             the owning domain from it, so the domain must
     *                             already be verified on the account.
     *
     * @throws \InvalidArgumentException on an empty name/recipient or a non-HTTPS URL
     */
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly string $recipient,
        private readonly bool $includeAttachments = false,
        private readonly bool $includeHeaders = false,
        private readonly bool $groupByMessageId = false,
        private readonly bool $stripReplies = false,
        private readonly bool $enabled = true,
    ) {
        if (trim($this->name) === '' || strlen($this->name) > 255) {
            throw new \InvalidArgumentException('Route name must be between 1 and 255 characters.');
        }

        if (trim($this->recipient) === '' || strlen($this->recipient) > 255) {
            throw new \InvalidArgumentException('Route recipient must be between 1 and 255 characters.');
        }

        if (filter_var($this->url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException("Invalid route URL: {$this->url}");
        }
    }

    public function resolveEndpoint(): string
    {
        return '/routes';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'name'                => $this->name,
            'url'                 => $this->url,
            'recipient'           => $this->recipient,
            'attachments'         => $this->includeAttachments,
            'headers'             => $this->includeHeaders,
            'group_by_message_id' => $this->groupByMessageId,
            'strip_replies'       => $this->stripReplies,
            'enabled'             => $this->enabled,
        ];
    }
}
