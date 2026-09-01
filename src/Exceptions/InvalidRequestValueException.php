<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Exceptions;

use DJZT\RequestHelper\Support\MessageResolver;
use Illuminate\Validation\ValidationException;

/**
 * Thrown when a request value is present but cannot be cast to the requested type.
 *
 * It extends Laravel's ValidationException, so an uncaught instance is rendered as
 * a regular 422 response (or a redirect back with errors) instead of a 500.
 */
class InvalidRequestValueException extends ValidationException
{
    /**
     * @param  string  $key  The request key that failed, used as the error bag key.
     * @param  string  $rule  A validation rule name, used to look up the message (e.g. "integer").
     * @param  array<string, string>  $replace
     */
    public static function for(string $key, string $rule, array $replace = []): static
    {
        return static::withMessages([
            $key => MessageResolver::resolve($key, $rule, $replace),
        ]);
    }
}
