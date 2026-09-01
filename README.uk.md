# Laravel Helpers

[English](README.md) · **Українська**

Два набори хелперів, які доводиться переписувати в кожному Laravel-проєкті, в
одному пакеті: **типізовані аксесори запиту**, що розрізняють «немає ключа», «null»
і «0», та **хелпери форматування для ресурсів**, які зводять формат дат усього API
до одного ключа в конфізі.

```php
// Запит, який знає, що насправді надіслали
$request->requiredInteger('qty');   // int               — 422, якщо ключа немає, він null або не приводиться
$request->nullableInteger('qty');   // ?int              — null, якщо ключа немає або він явно null
$request->optionalInteger('qty');   // int|null|Optional — Optional, якщо ключа немає

// Ресурс, який форматує сам себе
'created_at' => $this->date($this->created_at),   // "2026-09-01"
'price'      => $this->money($this->price),       // "1999.50"
'is_active'  => $this->bool($this->is_active),    // true
'status'     => $this->enum($this->status),       // "published"
```

Половини незалежні — беріть одну, другу або обидві.

## Встановлення

```bash
composer require djzt/laravel-helpers
```

Провайдер підхоплюється автоматично. Публікація конфігів необов'язкова:

```bash
php artisan vendor:publish --tag=helpers-config     # обидва файли одразу
php artisan vendor:publish --tag=request-helper-config
php artisan vendor:publish --tag=resource-helper-config
```

Потрібні PHP 8.2+ і Laravel 12 або 13. Laravel 11 не підтримується: кожен реліз 11.x
позначений як вразливий у базі Packagist, тож Composer відмовляється його ставити
за типовою політикою безпеки.

Потрібна лише одна половина? Зареєструйте її провайдер вручну, оминувши
автопідхоплення:

```php
// config/app.php — або bootstrap/providers.php
DJZT\RequestHelper\RequestHelperServiceProvider::class,
Djzt\ResourceHelper\ResourceHelperServiceProvider::class,
```

Переходите з `djzt/laravel-request-helper` чи `djzt/laravel-resource-helper`?
Дивіться [UPGRADE.md](UPGRADE.md) — імена класів не змінилися.

## Хелпери запиту

### Навіщо

Вбудовані аксесори Laravel зводять три різні стани тіла запиту до одного значення:

```php
$request->integer('qty');   // 0     — для {"qty": 0}, {"qty": null} і {}
$request->boolean('active') // false — для {"active": false}, {"active": null} і {}
```

Для `PATCH`-ендпоїнта це справжня проблема: «встановити кількість у 0», «очистити
кількість» і «не чіпати кількість» виглядають однаково. Цей пакет розрізняє всі три
стани й приводить типи суворо, тож значення, яке прослизнуло повз `rules()`, дає 422,
а не мовчки перетворюється на `0`.

### Використання

#### Успадкування від `BaseRequest`

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

#### Будь-який запит, без базового класу

Провайдер реєструє макрос `typed()` на `Illuminate\Http\Request`:

```php
public function index(Request $request)
{
    $page = $request->typed()->nullableInteger('page', 1);
}
```

#### Будь-який масив

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

### Три сімейства

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

### `Optional` і DTO

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

### Правила приведення

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

### Конфігурація

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

## Хелпери ресурсів

### Підключення

Або успадковуєтесь від базового ресурсу:

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

Або підключаєте трейт до наявного ресурсу — переписувати нічого не треба:

```php
use Djzt\ResourceHelper\Concerns\HasResourceHelpers;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    use HasResourceHelpers;
}
```

Поза ресурсами ті самі методи доступні через фасад:

```php
use Djzt\ResourceHelper\Facades\ResourceHelper;

ResourceHelper::date($order->created_at);
```

### Дати — основне

`$this->date()` бере формат із `config('resource-helper.formats.date')`,
тож формат дат у всьому API змінюється в одному місці.

```php
$this->date($this->created_at);            // 2026-09-01
$this->date($this->created_at, 'human');   // 1 Sep 2026     — пресет із конфіга
$this->date($this->created_at, 'd/m/Y');   // 01/09/2026     — сирий PHP-формат
$this->date($this->created_at, null, 'Europe/Kyiv');
```

Другий аргумент розв'язується так: якщо рядок є серед ключів `formats`
у конфізі — це іменований пресет; якщо ні — він використовується як
звичайний формат `date()`. Обидва стилі працюють одночасно.

На вхід приймається `Carbon`, будь-який `DateTimeInterface`, рядок або
unix-час (зокрема рядок із самих цифр). Порожнє значення (`null` або `''`)
перетворюється на `config('resource-helper.null_value')` — типово `null`.
Вихідний об'єкт дати не мутується.

#### Конфіг

```php
// config/resource-helper.php

'formats' => [
    'date'     => 'Y-m-d',        // <- формат $this->date()
    'datetime' => 'Y-m-d H:i:s',  // <- формат $this->dateTime()
    'time'     => 'H:i',          // <- формат $this->time()

    // іменовані пресети
    'iso'            => \DateTimeInterface::ATOM,
    'human'          => 'j M Y',
    'human_datetime' => 'j M Y, H:i',
],

// У який пояс переводити дати перед виведенням.
// null — як є; рядок — жорстко; замикання — наприклад, пояс користувача.
'timezone' => env('RESOURCE_HELPER_TIMEZONE'),

// Чим замінювати порожнє значення (деяким фронтам потрібен '' замість null).
'null_value' => null,

// true — кидати виняток на значенні, яке не розбирається, замість тихого null.
'strict' => false,
```

Часовий пояс на користувача:

```php
'timezone' => fn () => auth()->user()?->timezone,
```

#### Решта методів для дат

| Метод | Результат |
| --- | --- |
| `$this->date($v, $format = null, $tz = null)` | `"2026-09-01"` |
| `$this->dateTime($v, $format = null, $tz = null)` | `"2026-09-01 13:45:07"` |
| `$this->time($v, $format = null, $tz = null)` | `"13:45"` |
| `$this->isoDate($v)` | `"2026-09-01T13:45:07+00:00"` |
| `$this->timestamp($v)` | `1788270307` |
| `$this->diffForHumans($v)` | `"3 hours ago"` |
| `$this->dateArray($v)` | усі представлення одразу |
| `$this->dates([...])` | кілька дат за раз |

`dateArray()` віддає дату в усіх виглядах одночасно — зручно, коли фронту
потрібно і машиночитне значення для сортування, і готовий рядок для показу.
Набір ключів налаштовується в `config('resource-helper.date_array')`:

```php
'published_at' => $this->dateArray($this->published_at),

// "published_at": {
//     "raw":       "2026-09-01T13:45:07+00:00",
//     "formatted": "2026-09-01 13:45:07",
//     "timestamp": 1788270307,
//     "human":     "3 hours ago"
// }
```

`dates()` підмішується через spread, щоб не повторювати `date()` для кожного поля:

```php
return [
    'id' => $this->id,
    ...$this->dates([
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        'paid_at'    => [$this->paid_at, 'human'],   // свій формат для одного поля
    ]),
];
```

### Числа

| Метод | Навіщо |
| --- | --- |
| `$this->money($v, $currency = null)` | `"1999.50"` — знаки, роздільники, копійки та валюта з конфіга |
| `$this->number($v, $decimals = null)` / `float()` | `decimal` із БД приходить рядком `"10.00"` — у JSON потрібно `10.0` |
| `$this->integer($v)` / `int()` | `BIGINT` і `COUNT(*)` теж приходять рядком |
| `$this->boolean($v)` / `bool()` | `0` / `1` / `"1"` → справжні `true` / `false` |
| `$this->percent($v)` | `0.1234` → `12.34` |
| `$this->bytes($v)` | `1536` → `"1.5 KB"` |

`money()` налаштовується секцією `money` в конфізі: кількість знаків,
роздільники, `minor_units` (якщо в БД копійки) та вигляд результату —
рядок, `float` або масив `{amount, formatted, currency}`.

### Рядки, enum-и, файли

| Метод | Навіщо |
| --- | --- |
| `$this->string($v, $limit = null)` / `str()` | trim + обрізання довжини для прев'ю |
| `$this->enum($v)` | `BackedEnum` → `value`, звичайний enum → `name`, масиви поелементно |
| `$this->url($path, $disk = null)` | відносний шлях → абсолютне посилання на файл; уже абсолютне повертається як є |
| `$this->translate($v, $locale = null)` | json-поле `{"en": "...", "uk": "..."}` → рядок для поточної локалі з fallback |
| `$this->mask($v)` | `"380501234567"` → `"********4567"`, в e-mail маскується лише локальна частина |

### Умовні атрибути

```php
// Вкладений ресурс, але лише якщо зв'язок завантажено — інакше N+1.
// Колекція чи одна модель визначається автоматично.
'author'   => $this->whenLoadedResource('author', UserResource::class),
'comments' => $this->whenLoadedResource('comments', CommentResource::class),

// Атрибут лише для тих, кому дозволяє політика (Gate::allows($ability, $this->resource)).
'email' => $this->whenCan('viewEmail', fn () => $this->email),

// Атрибут лише для автентифікованих запитів.
'balance' => $this->whenAuthenticated(fn () => $this->balance),
```

Штатні `when()`, `whenLoaded()`, `whenCounted()`, `whenNotNull()` з Laravel
нікуди не зникають і працюють як зазвичай.

### Приклад цілком

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

## Тестування

```bash
composer install
composer test
```

## Ліцензія

MIT. Див. [LICENSE](LICENSE).
