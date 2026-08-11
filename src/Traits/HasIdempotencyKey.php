<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Traits;

use Illuminate\Support\Str;

/**
 * Adds the `Idempotency-Key` header Ahasend honours on write requests.
 *
 * Retries carrying the same key are collapsed server-side, so a create that is
 * retried after a timeout does not produce a second remote object.
 */
trait HasIdempotencyKey
{
    private ?string $idempotencyKey = null;

    /**
     * Use a caller-supplied key instead of a generated one.
     */
    public function withIdempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * The key sent with this request, generated once and then reused.
     */
    public function idempotencyKey(): string
    {
        return $this->idempotencyKey ??= (string) Str::uuid();
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['Idempotency-Key' => $this->idempotencyKey()];
    }
}
