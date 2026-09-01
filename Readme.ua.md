# Laravel Request Helper

[English](README.md) · **Українська**

Типізовані аксесори для вхідних даних запиту в Laravel, які дають відповідь на
питання, що його `$request->integer('qty')` дати не може: **чи взагалі був
надісланий ключ?**

```php
$request->requiredInteger('qty');   // int          — 422, якщо ключа немає, він null або не приводиться
$request->nullableInteger('qty');   // ?int         — null, якщо ключа немає або він явно null
$request->optionalInteger('qty');   // int|null|Optional — Optional, якщо ключа немає
```

## Навіщо

Вбудовані аксесори Laravel зводять три різні стани тіла запиту до одного значення:

```php
$request->integer('qty');   // 0     — для {"qty": 0}, {"qty": null} і {}
$request->boolean('active') // false — для {"active": false}, {"active": null} і {}
```

Для `PATCH`-ендпоїнта це справжня проблема: «встановити кількість у 0», «очистити
кількість» і «не чіпати кількість» виглядають однаково. Цей пакет розрізняє всі три
стани й приводить типи суворо, тож значення, яке прослизнуло повз `rules()`, дає 422,
а не мовчки перетворюється на `0`.

## Встановлення

```bash
composer require djzt/laravel-request-helper
```

Сервіс-провайдер підхоплюється автоматично. Публікація конфігу — за бажанням:

```bash
php artisan vendor:publish --tag=request-helper-config
```

Потрібні PHP 8.2+ і Laravel 12 або 13. Laravel 11 не підтримується: кожен реліз 11.x
позначено безпековими адвайзорі на Packagist, тож Composer за своєю типовою політикою
відмовляється його встановлювати.

Поки пакет на 0.x, публічний API ще може змінюватися; якщо потрібна стабільність,
фіксуйте мінорну версію (`^0.1`).

## Використання

### Успадкування від `BaseRequest`

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

Уже маєте власний базовий запит? Тоді підключіть трейт:

```php
use DJZT\RequestHelper\Concerns\InteractsWithTypedInput;

abstract class ApiRequest extends FormRequest
{
    use InteractsWithTypedInput;
}
```

### Будь-який запит, без базового класу

Провайдер реєструє макрос `typed()` на `Illuminate\Http\Request`:

```php
public function index(Request $request)
{
    $page = $request->typed()->nullableInteger('page', 1);
}
```

### Будь-який масив

`TypedInput` працює зі звичайними масивами та об'єктами `Arrayable` — це найпростіший
спосіб обмежити аксесори лише валідованими даними:

```php
use DJZT\RequestHelper\Support\TypedInput;

$input = TypedInput::for($request->validated());
$input->requiredInteger('qty');
```

Перевизначення `typedInput()` застосовує це до всіх аксесорів запиту одразу:

```php
abstract class ApiRequest extends BaseRequest
{
    protected function typedInput(): TypedInput
    {
        return TypedInput::for($this->validated());
    }
}
```

## Три сімейства

| тіло запиту          | `nullableX($key, $default)` | `optionalX($key)`  | `requiredX($key)` |
|----------------------|-----------------------------|--------------------|-------------------|
| `{}` (ключа немає)   | `$default` (`null`)         | `Optional`         | 422 `required`    |
| `{"x": null}`        | `null`                      | `null`             | 422 `required`    |
| `{"x": "42"}`        | `42`                        | `42`               | `42`              |
| `{"x": "abc"}`       | 422 `integer`               | 422 `integer`      | 422 `integer`     |

Доступні для кожного типу:

| Тип          | Повертає                 | Додаткові аргументи      |
|--------------|--------------------------|--------------------------|
| `Boolean`    | `bool`                   | —                        |
| `Integer`    | `int`                    | —                        |
| `Float`      | `float`                  | —                        |
| `String`     | `string`                 | —                        |
| `Array`      | `array`                  | —                        |
| `Collection` | `Illuminate\Support\Collection` | —                 |
| `Date`       | `Carbon\CarbonImmutable` | `$format`, `$timezone`   |
| `Enum`       | `BackedEnum`             | `$enum` (ім'я класу)     |

```php
$request->requiredEnum('status', Status::class);          // Status
$request->nullableDate('starts_at', 'd/m/Y', 'UTC');      // ?CarbonImmutable
$request->optionalCollection('tags');                     // Collection|null|Optional
```

Крапкова нотація працює скрізь: `$request->requiredInteger('meta.page.size')`.

## `Optional` і DTO

`Optional` — це маркер, а не обгортка: аксесор повертає або справжнє значення, або
екземпляр `Optional`, що добре читається як union-тип:

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

Застосувати лише те, що справді надіслали:

```php
$product->update(Optional::filter([
    'name' => $data->name,
    'qty' => $data->qty,   // явний null зберігається; відсутній ключ відкидається
]));
```

| Метод                               | Що робить                                                 |
|-------------------------------------|-----------------------------------------------------------|
| `Optional::create()`                | Новий маркер (те саме, що `new Optional`)                  |
| `Optional::isMissing($value)`       | `true`, якщо ключа не було                                 |
| `Optional::isPresent($value)`       | `true` для всього іншого, зокрема для `null`               |
| `Optional::value($value, $default)` | Розгортає значення, підставляючи `$default` за відсутності  |
| `Optional::filter($array)`          | Прибирає відсутні елементи, зберігаючи явні `null`         |

## Правила приведення

Приведення суворе й повторює відповідне правило валідації, тож усе, що проходить
`rules()`, проходить і тут:

- **boolean** — `true`/`false`, `1`/`0`, `"1"`, `"0"`, `"true"`, `"false"`, `"yes"`, `"no"`, `"on"`, `"off"`. Усе інше (зокрема `2`) відхиляється.
- **integer** — `int` або рядок чи `float` із цілим числом (`"42"`, `42.0`). `"1.5"` і булеві відхиляються.
- **float** — `int`, `float` або числовий рядок (`"1.5"`, `"1e3"`).
- **string** — `string`, `int`, `float` або будь-який `Stringable`. Масиви й булеві відхиляються.
- **array** — `array` або `Arrayable`.
- **collection** — як `array`, загорнутий у `Collection`.
- **date** — `DateTimeInterface`, unix-мітка часу як `int` або рядок, який розбирає Carbon (точно за `$format`, якщо його передано).
- **enum** — backing-значення кейса вказаного backed enum.

Значення, яке не вдалося привести, кидає `InvalidRequestValueException`; відсутнє або
`null`-значення в аксесорі `required*` кидає `MissingRequestValueException`. Обидва
успадковують `Illuminate\Validation\ValidationException`, тож неперехопленими вони
віддаються як звичайна відповідь 422 (або редірект назад з помилками) під ключем
запиту й з перекладеними повідомленнями валідації самого Laravel:

```json
{
    "message": "The qty field must be an integer.",
    "errors": { "qty": ["The qty field must be an integer."] }
}
```

Перехоплюйте їх явно, якщо потрібна інша поведінка:

```php
use DJZT\RequestHelper\Exceptions\MissingRequestValueException;

try {
    $qty = $request->requiredInteger('qty');
} catch (MissingRequestValueException) {
    $qty = $product->qty;
}
```

## Конфігурація

```php
return [
    // Реєструвати макрос typed() на Illuminate\Http\Request.
    'register_macro' => true,

    // Читати "" як null — так само, як middleware ConvertEmptyStringsToNull у Laravel.
    // Query-рядок не вміє передавати null: "?name=" приходить порожнім рядком.
    'empty_string_as_null' => true,
];
```

Перевизначення для окремого читача:

```php
TypedInput::for($request)->withEmptyStringAsNull(false)->nullableString('name'); // ""
```

## Тестування

```bash
composer install
composer test
```

## Ліцензія

MIT. Див. [LICENSE](LICENSE).
