<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Exceptions;

/**
 * Thrown when an owner already holds as many unverified domains as it may.
 *
 * The rule exists so an account cannot be filled with abandoned domains. It
 * costs a legitimate owner nothing: they verify one domain before adding the
 * next, and may always delete a pending one to free the slot.
 */
class DomainLimitExceededException extends AhasendException
{
    /**
     * @param  list<string>  $blockingDomains  The unverified domains in the way.
     */
    public function __construct(
        string $message,
        public readonly array $blockingDomains = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $blockingDomains
     */
    public static function forOwner(array $blockingDomains, int $limit): self
    {
        $names = implode(', ', $blockingDomains);

        return new self(
            "Cannot add another domain: {$limit} unverified domain(s) already exist ({$names}). "
            . 'Verify or delete them first.',
            $blockingDomains,
        );
    }
}
