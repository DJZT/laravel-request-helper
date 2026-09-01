<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Support;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use DJZT\RequestHelper\Exceptions\InvalidRequestValueException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionEnum;
use Stringable;
use Throwable;

/**
 * Factory of casting callbacks.
 *
 * Every callback receives an already non-null value plus the request key it came
 * from, and either returns the typed value or throws InvalidRequestValueException.
 * The accepted input mirrors Laravel's own validation rules as closely as possible,
 * so a value that passes `integer` in rules() also passes requiredInteger().
 */
final class Cast
{
    /**
     * @return Closure(mixed, string): bool
     */
    public static function boolean(): Closure
    {
        return static function (mixed $value, string $key): bool {
            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value) || is_int($value)) {
                $result = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($result !== null) {
                    return $result;
                }
            }

            throw InvalidRequestValueException::for($key, 'boolean');
        };
    }

    /**
     * @return Closure(mixed, string): int
     */
    public static function integer(): Closure
    {
        return static function (mixed $value, string $key): int {
            if (is_int($value)) {
                return $value;
            }

            // filter_var() happily turns booleans into 0/1, which is never what an
            // "integer" field means, so they are rejected up front.
            if (is_string($value) || is_float($value)) {
                $result = filter_var($value, FILTER_VALIDATE_INT);

                if ($result !== false) {
                    return $result;
                }
            }

            throw InvalidRequestValueException::for($key, 'integer');
        };
    }

    /**
     * @return Closure(mixed, string): float
     */
    public static function float(): Closure
    {
        return static function (mixed $value, string $key): float {
            if (is_float($value)) {
                return $value;
            }

            if (is_int($value)) {
                return (float) $value;
            }

            if (is_string($value)) {
                $result = filter_var($value, FILTER_VALIDATE_FLOAT);

                if ($result !== false) {
                    return $result;
                }
            }

            throw InvalidRequestValueException::for($key, 'numeric');
        };
    }

    /**
     * @return Closure(mixed, string): string
     */
    public static function string(): Closure
    {
        return static function (mixed $value, string $key): string {
            if (is_string($value)) {
                return $value;
            }

            if (is_int($value) || is_float($value) || $value instanceof Stringable) {
                return (string) $value;
            }

            throw InvalidRequestValueException::for($key, 'string');
        };
    }

    /**
     * @return Closure(mixed, string): array<array-key, mixed>
     */
    public static function array(): Closure
    {
        return static function (mixed $value, string $key): array {
            if (is_array($value)) {
                return $value;
            }

            if ($value instanceof Arrayable) {
                return $value->toArray();
            }

            throw InvalidRequestValueException::for($key, 'array');
        };
    }

    /**
     * @return Closure(mixed, string): Collection<array-key, mixed>
     */
    public static function collection(): Closure
    {
        $toArray = self::array();

        return static function (mixed $value, string $key) use ($toArray): Collection {
            if ($value instanceof Collection) {
                return $value;
            }

            return Collection::make($toArray($value, $key));
        };
    }

    /**
     * @param  string|null  $format  A DateTime format the value must match exactly.
     * @return Closure(mixed, string): CarbonImmutable
     */
    public static function date(?string $format = null, ?string $timezone = null): Closure
    {
        return static function (mixed $value, string $key) use ($format, $timezone): CarbonImmutable {
            if ($value instanceof DateTimeInterface) {
                $date = CarbonImmutable::instance($value);

                return $timezone === null ? $date : $date->setTimezone($timezone);
            }

            if (! is_string($value) && ! is_int($value)) {
                throw InvalidRequestValueException::for($key, 'date');
            }

            try {
                $date = is_int($value)
                    ? CarbonImmutable::createFromTimestamp($value, $timezone)
                    : ($format === null
                        ? CarbonImmutable::parse($value, $timezone)
                        : CarbonImmutable::createFromFormat($format, $value, $timezone));
            } catch (Throwable) {
                throw InvalidRequestValueException::for($key, 'date');
            }

            if (! $date instanceof CarbonImmutable) {
                throw InvalidRequestValueException::for($key, 'date');
            }

            return $date;
        };
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return Closure(mixed, string): TEnum
     */
    public static function enum(string $enum): Closure
    {
        if (! is_subclass_of($enum, BackedEnum::class)) {
            // A bad class name is a programming error, not bad user input.
            throw new InvalidArgumentException(sprintf(
                '[%s] is not a backed enum.', $enum
            ));
        }

        $isIntBacked = (string) (new ReflectionEnum($enum))->getBackingType() === 'int';

        return static function (mixed $value, string $key) use ($enum, $isIntBacked): BackedEnum {
            if ($value instanceof $enum) {
                return $value;
            }

            // Under strict_types the backing value has to match exactly, so a
            // query string "2" is normalised before reaching tryFrom().
            if ($isIntBacked) {
                $value = is_int($value) ? $value : (is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false);

                if ($value === false) {
                    throw InvalidRequestValueException::for($key, 'enum');
                }
            } elseif (is_int($value)) {
                $value = (string) $value;
            } elseif (! is_string($value)) {
                throw InvalidRequestValueException::for($key, 'enum');
            }

            return $enum::tryFrom($value) ?? throw InvalidRequestValueException::for($key, 'enum');
        };
    }
}
