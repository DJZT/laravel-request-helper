<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Support;

use Closure;
use DJZT\RequestHelper\Concerns\InteractsWithTypedInput;
use DJZT\RequestHelper\Exceptions\MissingRequestValueException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Reads typed values out of a request (or any array-ish payload).
 *
 * The class carries the whole nullable / optional / required surface through
 * InteractsWithTypedInput, so it can be used standalone:
 *
 *     TypedInput::for($request->validated())->requiredInteger('page');
 */
final class TypedInput
{
    use InteractsWithTypedInput;

    /**
     * @param  Closure(string, mixed): mixed  $reader  Resolves a key, returning the given fallback when absent.
     */
    private function __construct(
        private readonly Closure $reader,
        private bool $emptyStringAsNull,
    ) {}

    /**
     * @param  Request|Arrayable<array-key, mixed>|array<array-key, mixed>  $source
     */
    public static function for(Request|Arrayable|array $source): self
    {
        $reader = $source instanceof Request
            ? static fn (string $key, mixed $fallback): mixed => $source->input($key, $fallback)
            : self::arrayReader($source instanceof Arrayable ? $source->toArray() : $source);

        return new self($reader, (bool) Settings::get('empty_string_as_null', true));
    }

    /**
     * Toggle, for this reader only, whether "" is treated as null.
     */
    public function withEmptyStringAsNull(bool $emptyStringAsNull = true): self
    {
        $clone = clone $this;
        $clone->emptyStringAsNull = $emptyStringAsNull;

        return $clone;
    }

    /**
     * Determine whether the key is present in the payload, even with a null value.
     */
    public function has(string $key): bool
    {
        return $this->rawValue($key) !== Missing::instance();
    }

    /**
     * Cast the value, keeping an explicit null; absent keys fall back to $default.
     *
     * @param  Closure(mixed, string): mixed  $cast
     */
    public function nullable(string $key, Closure $cast, mixed $default = null): mixed
    {
        $value = $this->value($key);

        if ($value === Missing::instance()) {
            return $default;
        }

        return $value === null ? null : $cast($value, $key);
    }

    /**
     * Cast the value, returning an Optional marker when the key is absent entirely.
     *
     * @param  Closure(mixed, string): mixed  $cast
     */
    public function optional(string $key, Closure $cast): mixed
    {
        $value = $this->value($key);

        if ($value === Missing::instance()) {
            return Optional::create();
        }

        return $value === null ? null : $cast($value, $key);
    }

    /**
     * Cast the value, refusing both an absent key and an explicit null.
     *
     * @param  Closure(mixed, string): mixed  $cast
     *
     * @throws MissingRequestValueException
     */
    public function required(string $key, Closure $cast): mixed
    {
        $value = $this->value($key);

        if ($value === Missing::instance() || $value === null) {
            throw MissingRequestValueException::key($key);
        }

        return $cast($value, $key);
    }

    protected function typedInput(): self
    {
        return $this;
    }

    /**
     * The raw value, with "" normalised to null when configured to do so.
     */
    private function value(string $key): mixed
    {
        $value = $this->rawValue($key);

        if ($this->emptyStringAsNull && $value === '') {
            return null;
        }

        return $value;
    }

    private function rawValue(string $key): mixed
    {
        return ($this->reader)($key, Missing::instance());
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return Closure(string, mixed): mixed
     */
    private static function arrayReader(array $data): Closure
    {
        return static fn (string $key, mixed $fallback): mixed => Arr::get($data, $key, $fallback);
    }
}
