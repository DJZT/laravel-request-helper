<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Exceptions;

/**
 * Thrown by the required* helpers when a key is absent or explicitly null.
 */
final class MissingRequestValueException extends InvalidRequestValueException
{
    public static function key(string $key): self
    {
        return self::for($key, 'required');
    }
}
