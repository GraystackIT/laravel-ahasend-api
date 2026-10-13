<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * What a screen decided about a delivery before any listener sees it.
 */
final readonly class ScreenVerdict
{
    private const string ACCEPT = 'accept';

    private const string DISCARD = 'discard';

    private const string DEFER = 'defer';

    /**
     * @param  string  $decision  One of accept, discard or defer.
     * @param  int  $delaySeconds  How long to wait before asking again, for a deferral.
     */
    private function __construct(
        public string $decision,
        public int $delaySeconds = 0,
    ) {}

    /**
     * Hand the delivery to the listeners.
     */
    public static function accept(): self
    {
        return new self(self::ACCEPT);
    }

    /**
     * Drop the delivery: no listener ever sees it and its payload is deleted.
     */
    public static function discard(): self
    {
        return new self(self::DISCARD);
    }

    /**
     * Ask again later, without handing the delivery on in the meantime.
     *
     * @param  int  $seconds  Delay before the next attempt; at least one second.
     */
    public static function defer(int $seconds): self
    {
        return new self(self::DEFER, max(1, $seconds));
    }

    /**
     * Whether the listeners get it.
     */
    public function accepts(): bool
    {
        return $this->decision === self::ACCEPT;
    }

    /**
     * Whether it is dropped for good.
     */
    public function discards(): bool
    {
        return $this->decision === self::DISCARD;
    }

    /**
     * Whether it is to be asked about again later.
     */
    public function defers(): bool
    {
        return $this->decision === self::DEFER;
    }
}
