# Laravel Helpers

**English** · [Українська](README.uk.md)

Two helper sets that Laravel projects end up rewriting in every codebase, in one
package: **typed request accessors** that keep "absent", "null" and "0" apart, and
**resource formatting helpers** that put the date format of your whole API in one
config key.

```php
// A request that knows what was actually sent
$request->requiredInteger('qty');   // int               — 422 if absent, null or uncastable
$request->nullableInteger('qty');   // ?int              — null when absent or explicitly null
$request->optionalInteger('qty');   // int|null|Optional — Optional when the key is absent

// A resource that formats itself
'created_at' => $this->date($this->created_at),   // "2026-09-01"
'price'      => $this->money($this->price),       // "1999.50"
'is_active'  => $this->bool($this->is_active),    // true
'status'     => $this->enum($this->status),       // "published"
```

The two halves are independent — take one, the other, or both.

## Installation

```bash
composer require djzt/laravel-helpers
```

The service provider is auto-discovered. Publishing the config is optional:

```bash
php artisan vendor:publish --tag=helpers-config     # both files at once
php artisan vendor:publish --tag=request-helper-config
php artisan vendor:publish --tag=resource-helper-config
```

Requires PHP 8.2+ and Laravel 12 or 13. Laravel 11 is not supported: every 11.x
release is flagged by Packagist security advisories, so Composer refuses to install
it under its default advisory policy.

Only want one half? Register its provider yourself and skip auto-discovery:

```php
// config/app.php — or bootstrap/providers.php
DJZT\RequestHelper\RequestHelperServiceProvider::class,
Djzt\ResourceHelper\ResourceHelperServiceProvider::class,
```

Coming from `djzt/laravel-request-helper` or `djzt/laravel-resource-helper`?
See [UPGRADE.md](UPGRADE.md) — the class names did not change.

## Request helpers

### Why

Laravel's built-in accessors collapse three different payload states into one value:

```php
$request->integer('qty');   // 0     — for {"qty": 0}, {"qty": null} and {}
$request->boolean('active') // false — for {"active": false}, {"active": null} and {}
```

For a `PATCH` endpoint that is a real problem: "set quantity to 0", "clear the
quantity" and "don't touch the quantity" all look identical. This package keeps the
three states apart and casts strictly, so a value that slipped past `rules()`
raises a 422 instead of silently becoming `0`.

### Usage

#### Extend `BaseRequest`

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

#### Any request, without a base class

The provider registers a `typed()` macro on `Illuminate\Http\Request`:

```php
public function index(Request $request)
{
    $page = $request->typed()->nullableInteger('page', 1);
}
```

#### Any array

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

### The three families

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

### `Optional` and DTOs

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

### Casting rules

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

### Configuration

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

## Resource helpers

### Setup

Either extend the base resource:

```php
use Djzt\ResourceHelper\HelperResource;

class PostResource extends HelperResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->string($this->title),
            'created_at' => $this->date($this->created_at),
        ];
    }
}
```

Or pull the trait into an existing resource — nothing else has to change:

```php
use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    use HasResourceHelpers;
}
```

Outside of resources the same methods are available through the facade:

```php
use Djzt\ResourceHelper\Facades\ResourceHelper;

ResourceHelper::date($order->created_at);
```

### Dates — the main feature

`$this->date()` takes its format from `config('resource-helper.formats.date')`,
so the date format across your entire API changes in one place.

```php
$this->date($this->created_at);            // 2026-09-01
$this->date($this->created_at, 'human');   // 1 Sep 2026     — preset from config
$this->date($this->created_at, 'd/m/Y');   // 01/09/2026     — raw PHP format
$this->date($this->created_at, null, 'Europe/Kyiv');
```

The second argument resolves like this: if the string is a key in the config's
`formats` array, it is a named preset; otherwise it is used verbatim as a PHP
`date()` format. Both styles work side by side.

Accepted input: `Carbon`, any `DateTimeInterface`, a string, or a unix timestamp
(including a digits-only string). An empty value (`null` or `''`) becomes
`config('resource-helper.null_value')`, which defaults to `null`.
The original date object is never mutated.

#### Configuration

```php
// config/resource-helper.php

'formats' => [
    'date'     => 'Y-m-d',        // <- used by $this->date()
    'datetime' => 'Y-m-d H:i:s',  // <- used by $this->dateTime()
    'time'     => 'H:i',          // <- used by $this->time()

    // named presets
    'iso'            => \DateTimeInterface::ATOM,
    'human'          => 'j M Y',
    'human_datetime' => 'j M Y, H:i',
],

// Timezone to convert dates into before formatting.
// null — leave as is; a string — fixed for the whole API;
// a closure — e.g. the current user's timezone.
'timezone' => env('RESOURCE_HELPER_TIMEZONE'),

// What to return instead of an empty value (some frontends want '' over null).
'null_value' => null,

// true — throw on an unparsable value instead of silently returning null.
'strict' => false,
```

Per-user timezone:

```php
'timezone' => fn () => auth()->user()?->timezone,
```

#### The other date methods

| Method | Result |
| --- | --- |
| `$this->date($v, $format = null, $tz = null)` | `"2026-09-01"` |
| `$this->dateTime($v, $format = null, $tz = null)` | `"2026-09-01 13:45:07"` |
| `$this->time($v, $format = null, $tz = null)` | `"13:45"` |
| `$this->isoDate($v)` | `"2026-09-01T13:45:07+00:00"` |
| `$this->timestamp($v)` | `1788270307` |
| `$this->diffForHumans($v)` | `"3 hours ago"` |
| `$this->dateArray($v)` | every representation at once |
| `$this->dates([...])` | several dates in one call |

`dateArray()` returns the date in all forms at once — handy when the frontend
needs both a machine-readable value for sorting and a ready-to-display string.
The set of keys is configured in `config('resource-helper.date_array')`:

```php
'published_at' => $this->dateArray($this->published_at),

// "published_at": {
//     "raw":       "2026-09-01T13:45:07+00:00",
//     "formatted": "2026-09-01 13:45:07",
//     "timestamp": 1788270307,
//     "human":     "3 hours ago"
// }
```

`dates()` is meant to be spread in, so you don't repeat `date()` per field:

```php
return [
    'id' => $this->id,
    ...$this->dates([
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        'paid_at'    => [$this->paid_at, 'human'],   // per-field format
    ]),
];
```

### Numbers

| Method | Why |
| --- | --- |
| `$this->money($v, $currency = null)` | `"1999.50"` — decimals, separators, minor units and currency from config |
| `$this->number($v, $decimals = null)` / `float()` | a `decimal` column arrives as the string `"10.00"`; JSON wants `10.0` |
| `$this->integer($v)` / `int()` | `BIGINT` and `COUNT(*)` also arrive as strings |
| `$this->boolean($v)` / `bool()` | `0` / `1` / `"1"` → a real `true` / `false` |
| `$this->percent($v)` | `0.1234` → `12.34` |
| `$this->bytes($v)` | `1536` → `"1.5 KB"` |

`money()` is driven by the `money` section of the config: number of decimals,
separators, `minor_units` (when the database stores cents), and the shape of the
result — a string, a `float`, or an array of `{amount, formatted, currency}`.

### Strings, enums, files

| Method | Why |
| --- | --- |
| `$this->string($v, $limit = null)` / `str()` | trim plus optional truncation for previews |
| `$this->enum($v)` | `BackedEnum` → `value`, pure enum → `name`, arrays element-wise |
| `$this->url($path, $disk = null)` | a relative path → an absolute file URL; an already absolute one is returned untouched |
| `$this->translate($v, $locale = null)` | a JSON column like `{"en": "...", "uk": "..."}` → the string for the current locale, with fallback |
| `$this->mask($v)` | `"380501234567"` → `"********4567"`; for an e-mail only the local part is masked |

### Conditional attributes

```php
// A nested resource, but only when the relation is loaded — otherwise N+1.
// Collection vs. single model is detected automatically.
'author'   => $this->whenLoadedResource('author', UserResource::class),
'comments' => $this->whenLoadedResource('comments', CommentResource::class),

// An attribute only for those the policy allows (Gate::allows($ability, $this->resource)).
'email' => $this->whenCan('viewEmail', fn () => $this->email),

// An attribute only for authenticated requests.
'balance' => $this->whenAuthenticated(fn () => $this->balance),
```

Laravel's own `when()`, `whenLoaded()`, `whenCounted()` and `whenNotNull()` are
untouched and keep working as usual.

### A full example

```php
class PostResource extends HelperResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->translate($this->title),
            'excerpt'     => $this->string($this->body, 160),
            'status'      => $this->enum($this->status),
            'is_featured' => $this->bool($this->is_featured),
            'views'       => $this->int($this->views),
            'price'       => $this->money($this->price),
            'cover'       => $this->url($this->cover_path),
            'attachment'  => $this->bytes($this->attachment_size),

            'published_at' => $this->dateArray($this->published_at),
            ...$this->dates([
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),

            'author'   => $this->whenLoadedResource('author', UserResource::class),
            'comments' => $this->whenLoadedResource('comments', CommentResource::class),
        ];
    }
}
```

## Testing

```bash
composer install
composer test
```

## License

MIT. See [LICENSE](LICENSE).
