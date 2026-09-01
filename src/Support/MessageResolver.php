<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Support;

use Illuminate\Support\Str;
use Throwable;

/**
 * Builds validation messages that look exactly like the ones Laravel's validator
 * produces, so a failing helper is indistinguishable from a failing rule in the
 * 422 response body.
 *
 * @internal
 */
final class MessageResolver
{
    /**
     * Fallbacks used when no translator is available (or the lang files are missing).
     */
    private const FALLBACKS = [
        'array' => 'The :attribute field must be an array.',
        'boolean' => 'The :attribute field must be true or false.',
        'date' => 'The :attribute field must be a valid date.',
        'enum' => 'The selected :attribute is invalid.',
        'integer' => 'The :attribute field must be an integer.',
        'numeric' => 'The :attribute field must be a number.',
        'required' => 'The :attribute field is required.',
        'string' => 'The :attribute field must be a string.',
    ];

    /**
     * @param  array<string, string>  $replace
     */
    public static function resolve(string $key, string $rule, array $replace = []): string
    {
        $replace = ['attribute' => self::attribute($key)] + $replace;

        return self::translate('validation.'.$rule, $replace)
            ?? self::replace(self::FALLBACKS[$rule] ?? 'The :attribute field is invalid.', $replace);
    }

    /**
     * Mirrors Validator::getDisplayableAttribute(): custom names first, snake case otherwise.
     */
    private static function attribute(string $key): string
    {
        $custom = self::translate('validation.attributes.'.$key, []);

        if ($custom !== null) {
            return $custom;
        }

        return str_replace('_', ' ', Str::snake(str_replace('.', ' ', $key)));
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function translate(string $line, array $replace): ?string
    {
        if (! function_exists('trans')) {
            return null;
        }

        try {
            $message = trans($line, $replace);
        } catch (Throwable) {
            return null;
        }

        // The translator echoes the key back when the line is missing.
        return is_string($message) && $message !== $line ? $message : null;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function replace(string $message, array $replace): string
    {
        foreach ($replace as $search => $value) {
            $message = str_replace(':'.$search, $value, $message);
        }

        return $message;
    }
}
