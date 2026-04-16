<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Exceptions;

use RuntimeException;
use Saloon\Exceptions\Request\RequestException;

class AhasendException extends RuntimeException
{
    /**
     * Create an exception from a Saloon RequestException.
     */
    public static function fromRequestException(RequestException $e): self
    {
        $status  = $e->getResponse()->status();
        $body    = $e->getResponse()->body();
        $message = "Ahasend API error [{$status}]: {$body}";

        return new self($message, $status, $e);
    }

    /**
     * Create a generic exception with a custom message.
     */
    public static function make(string $message, int $code = 0, ?\Throwable $previous = null): self
    {
        return new self($message, $code, $previous);
    }
}
