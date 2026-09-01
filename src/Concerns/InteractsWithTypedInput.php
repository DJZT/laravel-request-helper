<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Concerns;

use BackedEnum;
use Carbon\CarbonImmutable;
use DJZT\RequestHelper\Support\Cast;
use DJZT\RequestHelper\Support\Optional;
use DJZT\RequestHelper\Support\TypedInput;
use Illuminate\Support\Collection;

/**
 * Typed accessors for request input.
 *
 * Three families, one per way a key can be missing:
 *
 * | value in payload | nullable*  | optional*    | required*  |
 * |------------------|------------|--------------|------------|
 * | key absent       | $default   | new Optional | exception  |
 * | null             | null       | null         | exception  |
 * | "42"             | 42         | 42           | 42         |
 *
 * A present value that cannot be cast always throws InvalidRequestValueException,
 * which renders as a 422 response.
 */
trait InteractsWithTypedInput
{
    /**
     * The reader backing every accessor below.
     *
     * Override this to read from somewhere else — `TypedInput::for($this->validated())`
     * restricts the helpers to validated data, for instance.
     */
    protected function typedInput(): TypedInput
    {
        return TypedInput::for($this);
    }

    public function nullableBoolean(string $key, ?bool $default = null): ?bool
    {
        return $this->typedInput()->nullable($key, Cast::boolean(), $default);
    }

    public function optionalBoolean(string $key): bool|Optional|null
    {
        return $this->typedInput()->optional($key, Cast::boolean());
    }

    public function requiredBoolean(string $key): bool
    {
        return $this->typedInput()->required($key, Cast::boolean());
    }

    public function nullableInteger(string $key, ?int $default = null): ?int
    {
        return $this->typedInput()->nullable($key, Cast::integer(), $default);
    }

    public function optionalInteger(string $key): int|Optional|null
    {
        return $this->typedInput()->optional($key, Cast::integer());
    }

    public function requiredInteger(string $key): int
    {
        return $this->typedInput()->required($key, Cast::integer());
    }

    public function nullableFloat(string $key, ?float $default = null): ?float
    {
        return $this->typedInput()->nullable($key, Cast::float(), $default);
    }

    public function optionalFloat(string $key): float|Optional|null
    {
        return $this->typedInput()->optional($key, Cast::float());
    }

    public function requiredFloat(string $key): float
    {
        return $this->typedInput()->required($key, Cast::float());
    }

    public function nullableString(string $key, ?string $default = null): ?string
    {
        return $this->typedInput()->nullable($key, Cast::string(), $default);
    }

    public function optionalString(string $key): string|Optional|null
    {
        return $this->typedInput()->optional($key, Cast::string());
    }

    public function requiredString(string $key): string
    {
        return $this->typedInput()->required($key, Cast::string());
    }

    /**
     * @param  array<array-key, mixed>|null  $default
     * @return array<array-key, mixed>|null
     */
    public function nullableArray(string $key, ?array $default = null): ?array
    {
        return $this->typedInput()->nullable($key, Cast::array(), $default);
    }

    /**
     * @return array<array-key, mixed>|Optional|null
     */
    public function optionalArray(string $key): array|Optional|null
    {
        return $this->typedInput()->optional($key, Cast::array());
    }

    /**
     * @return array<array-key, mixed>
     */
    public function requiredArray(string $key): array
    {
        return $this->typedInput()->required($key, Cast::array());
    }

    /**
     * @param  Collection<array-key, mixed>|null  $default
     * @return Collection<array-key, mixed>|null
     */
    public function nullableCollection(string $key, ?Collection $default = null): ?Collection
    {
        return $this->typedInput()->nullable($key, Cast::collection(), $default);
    }

    /**
     * @return Collection<array-key, mixed>|Optional|null
     */
    public function optionalCollection(string $key): Collection|Optional|null
    {
        return $this->typedInput()->optional($key, Cast::collection());
    }

    /**
     * @return Collection<array-key, mixed>
     */
    public function requiredCollection(string $key): Collection
    {
        return $this->typedInput()->required($key, Cast::collection());
    }

    public function nullableDate(string $key, ?string $format = null, ?string $timezone = null): ?CarbonImmutable
    {
        return $this->typedInput()->nullable($key, Cast::date($format, $timezone));
    }

    public function optionalDate(string $key, ?string $format = null, ?string $timezone = null): CarbonImmutable|Optional|null
    {
        return $this->typedInput()->optional($key, Cast::date($format, $timezone));
    }

    public function requiredDate(string $key, ?string $format = null, ?string $timezone = null): CarbonImmutable
    {
        return $this->typedInput()->required($key, Cast::date($format, $timezone));
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  TEnum|null  $default
     * @return TEnum|null
     */
    public function nullableEnum(string $key, string $enum, ?BackedEnum $default = null): ?BackedEnum
    {
        return $this->typedInput()->nullable($key, Cast::enum($enum), $default);
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|Optional|null
     */
    public function optionalEnum(string $key, string $enum): BackedEnum|Optional|null
    {
        return $this->typedInput()->optional($key, Cast::enum($enum));
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    public function requiredEnum(string $key, string $enum): BackedEnum
    {
        return $this->typedInput()->required($key, Cast::enum($enum));
    }
}
