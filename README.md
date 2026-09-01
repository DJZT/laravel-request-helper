# Laravel Request Helper

**English** · [Українська](Readme.ua.md)

Typed accessors for Laravel request input, with an explicit answer to the question
`$request->integer('qty')` cannot answer: **was the key sent at all?**

```php
$request->requiredInteger('qty');   // int          — 422 if absent, null or uncastable
$request->nullableInteger('qty');   // ?int         — null when absent or explicitly null
$request->optionalInteger('qty');   // int|null|Optional — Optional when the key is absent
```

## Why

Laravel's built-in accessors collapse three different payload states into one value:

```php
$request->integer('qty');   // 0     — for {"qty": 0}, {"qty": null} and {}
$request->boolean('active') // false — for {"active": false}, {"active": null} and {}
```

For a `PATCH` endpoint that is a real problem: "set quantity to 0", "clear the
quantity" and "don't touch the quantity" all look identical. This package keeps the
three states apart and casts strictly, so a value that slipped past `rules()`
raises a 422 instead of silently becoming `0`.

## Installation

```bash
composer require djzt/laravel-request-helper
```

The service provider is auto-discovered. Publishing the config is optional:

```bash
php artisan vendor:publish --tag=request-helper-config
```

Requires PHP 8.2+ and Laravel 12 or 13. Laravel 11 is not supported: every 11.x
release is flagged by Packagist security advisories, so Composer refuses to install
it under its default advisory policy.

While the package is on 0.x the public API may still change; pin a minor version
(`^0.1`) if you need stability.

## Usage

### Extend `BaseRequest`

```php
use DJZT\RequestHelper\Http\Requests\BaseRequest;

final class UpdateProductRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'qty' => ['sometimes', 'integer', 'min:0'],
            'name' => ['sometimes', 'string'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
```

```php
public function update(UpdateProductRequest $request, Product $product)
{
    $qty = $request->requiredInteger('qty');            // int
    $expiresAt = $request->nullableDate('expires_at');  // ?CarbonImmutable
    $name = $request->optionalString('name');           // string|null|Optional
}
```

Already have your own base request? Use the trait instead:

```php
use DJZT\RequestHelper\Concerns\InteractsWithTypedInput;

abstract class ApiRequest extends FormRequest
{
    use InteractsWithTypedInput;
}
```

### Any request, without a base class

The provider registers a `typed()` macro on `Illuminate\Http\Request`:

```php
public function index(Request $request)
{
    $page = $request->typed()->nullableInteger('page', 1);
}
```

### Any array

`TypedInput` works on plain arrays and `Arrayable` objects, which is the easiest way
to restrict the accessors to validated data:

```php
use DJZT\RequestHelper\Support\TypedInput;

$input = TypedInput::for($request->validated());
$input->requiredInteger('qty');
```

Overriding `typedInput()` applies that to every accessor on the request at once:

```php
abstract class ApiRequest extends BaseRequest
{
    protected function typedInput(): TypedInput
    {
        return TypedInput::for($this->validated());
    }
}
```

## The three families

| payload            | `nullableX($key, $default)` | `optionalX($key)`  | `requiredX($key)` |
|--------------------|-----------------------------|--------------------|-------------------|
| `{}` (key absent)  | `$default` (`null`)         | `Optional`         | 422 `required`    |
| `{"x": null}`      | `null`                      | `null`             | 422 `required`    |
| `{"x": "42"}`      | `42`                        | `42`               | `42`              |
| `{"x": "abc"}`     | 422 `integer`               | 422 `integer`      | 422 `integer`     |

Available for every type:

| Type         | Returns                  | Extra arguments        |
|--------------|--------------------------|------------------------|
| `Boolean`    | `bool`                   | —                      |
| `Integer`    | `int`                    | —                      |
| `Float`      | `float`                  | —                      |
| `String`     | `string`                 | —                      |
| `Array`      | `array`                  | —                      |
| `Collection` | `Illuminate\Support\Collection` | —               |
| `Date`       | `Carbon\CarbonImmutable` | `$format`, `$timezone` |
| `Enum`       | `BackedEnum`             | `$enum` (class name)   |

```php
$request->requiredEnum('status', Status::class);          // Status
$request->nullableDate('starts_at', 'd/m/Y', 'UTC');      // ?CarbonImmutable
$request->optionalCollection('tags');                     // Collection|null|Optional
```

Dot notation works everywhere: `$request->requiredInteger('meta.page.size')`.

## `Optional` and DTOs

`Optional` is a marker, not a wrapper — the accessor returns either the real value or
an `Optional` instance, which reads well as a union type:

```php
use DJZT\RequestHelper\Support\Optional;

final readonly class UpdateProductData
{
    public function __construct(
        public string|Optional $name = new Optional,
        public int|null|Optional $qty = new Optional,
    ) {}

    public static function from(UpdateProductRequest $request): self
    {
        return new self(
            name: $request->optionalString('name'),
            qty: $request->optionalInteger('qty'),
        );
    }
}
```

Applying only what was actually sent:

```php
$product->update(Optional::filter([
    'name' => $data->name,
    'qty' => $data->qty,   // an explicit null survives; an absent key is dropped
]));
```

| Helper                              | Does                                                     |
|-------------------------------------|----------------------------------------------------------|
| `Optional::create()`                | New marker (same as `new Optional`)                       |
| `Optional::isMissing($value)`       | `true` when the key was absent                            |
| `Optional::isPresent($value)`       | `true` for anything else, including `null`                |
| `Optional::value($value, $default)` | Unwrap, falling back to `$default` when absent            |
| `Optional::filter($array)`          | Drop absent entries, keep explicit nulls                  |

## Casting rules

Casting is strict and mirrors the matching validation rule, so anything that passes
`rules()` passes here too:

- **boolean** — `true`/`false`, `1`/`0`, `"1"`, `"0"`, `"true"`, `"false"`, `"yes"`, `"no"`, `"on"`, `"off"`. Anything else (including `2`) is rejected.
- **integer** — `int`, or a string/float holding a whole number (`"42"`, `42.0`). `"1.5"` and booleans are rejected.
- **float** — `int`, `float`, or a numeric string (`"1.5"`, `"1e3"`).
- **string** — `string`, `int`, `float` or any `Stringable`. Arrays and booleans are rejected.
- **array** — `array` or `Arrayable`.
- **collection** — as `array`, wrapped in a `Collection`.
- **date** — `DateTimeInterface`, a unix timestamp `int`, or a string parsed by Carbon (exactly matching `$format` when given).
- **enum** — the backing value of a case of the given backed enum.

A value that cannot be cast throws `InvalidRequestValueException`; an absent or null
value in a `required*` accessor throws `MissingRequestValueException`. Both extend
`Illuminate\Validation\ValidationException`, so uncaught they render as a normal 422
response (or a redirect back with errors), keyed by the request key and using
Laravel's own translated validation messages:

```json
{
    "message": "The qty field must be an integer.",
    "errors": { "qty": ["The qty field must be an integer."] }
}
```

Catch them explicitly when you want different handling:

```php
use DJZT\RequestHelper\Exceptions\MissingRequestValueException;

try {
    $qty = $request->requiredInteger('qty');
} catch (MissingRequestValueException) {
    $qty = $product->qty;
}
```

## Configuration

```php
return [
    // Register the typed() macro on Illuminate\Http\Request.
    'register_macro' => true,

    // Read "" as null, matching Laravel's ConvertEmptyStringsToNull middleware.
    // A query string cannot express null: "?name=" arrives as an empty string.
    'empty_string_as_null' => true,
];
```

Per-reader override:

```php
TypedInput::for($request)->withEmptyStringAsNull(false)->nullableString('name'); // ""
```

## Testing

```bash
composer install
composer test
```

## License

MIT. See [LICENSE](LICENSE).
