<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Exceptions;

/**
 * Thrown when a domain is already tracked locally.
 */
class DomainAlreadyExistsException extends AhasendException
{
    public static function for(string $domain): self
    {
        return new self("The domain {$domain} is already registered.");
    }
}
