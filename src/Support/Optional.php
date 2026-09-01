<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Support;

use JsonSerializable;

/**
 * Marker for a request key that was not present at all.
 *
 * It is intentionally *not* a wrapper: `optionalString('name')` returns either the
 * real value (which may be `null` when the key was sent as `null`) or an instance
 * of this class when the key was absent from the payload. That makes union types
 * such as `string|null|Optional` express the three states an HTTP payload can
 * actually carry, which is what PATCH-style partial updates need.
 */
final class Optional implements JsonSerializable
{
    /**
     * Create a marker instance.
     *
     * `new Optional()` works as a property/parameter default too:
     * `public function __construct(public readonly string|Optional $name = new Optional()) {}`
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Determine whether the given value stands for an absent key.
     *
     * @phpstan-assert-if-true Optional $value
     */
    public static function isMissing(mixed $value): bool
    {
        return $value instanceof self;
    }

    /**
     * Determine whether the given value was actually present in the payload.
     */
    public static function isPresent(mixed $value): bool
    {
        return ! $value instanceof self;
    }

    /**
     * Unwrap a value, falling back to $default when the key was absent.
     */
    public static function value(mixed $value, mixed $default = null): mixed
    {
        return $value instanceof self ? $default : $value;
    }

    /**
     * Drop every absent value from an array, keeping explicit nulls.
     *
     * Handy for turning a partial-update DTO into an `update()` payload.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function filter(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => ! $value instanceof self);
    }

    /**
     * Absent values serialize to null; use Optional::filter() to omit them entirely.
     */
    public function jsonSerialize(): mixed
    {
        return null;
    }
}
