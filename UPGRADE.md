# Upgrading

## From `djzt/laravel-request-helper` / `djzt/laravel-resource-helper` to `djzt/laravel-helpers` 1.0

The two packages were merged into one. **No class, trait, facade or config key
changed name**, so application code keeps working as it is — only the Composer
package and the service provider registration move.

### 1. Swap the requirement

```bash
composer remove djzt/laravel-request-helper djzt/laravel-resource-helper
composer require djzt/laravel-helpers
```

Requiring one of the old packages alongside the new one would load the same
classes twice, so remove them both.

### 2. Update manually registered providers

Auto-discovery needs nothing. If you listed the providers by hand in
`bootstrap/providers.php` (or `config/app.php`), replace both entries with one:

```php
- DJZT\RequestHelper\RequestHelperServiceProvider::class,
- Djzt\ResourceHelper\ResourceHelperServiceProvider::class,
+ DJZT\Helpers\HelpersServiceProvider::class,
```

Both original providers still exist and can still be registered on their own when
you want just one half of the package.

### 3. Everything else stays

- Class names: `DJZT\RequestHelper\…` and `Djzt\ResourceHelper\…` are unchanged.
- Config files: `config/request-helper.php` and `config/resource-helper.php` keep
  their names, keys and publish tags. Already-published files need no edits.
  `--tag=helpers-config` publishes both at once.
- The `ResourceHelper` facade alias and the `typed()` request macro are unchanged.

---

# Оновлення

## З `djzt/laravel-request-helper` / `djzt/laravel-resource-helper` на `djzt/laravel-helpers` 1.0

Два пакети об'єднано в один. **Жоден клас, трейт, фасад чи ключ конфіга не змінив
назви**, тож код застосунку працює як був — змінюються лише пакет у Composer і
реєстрація сервіс-провайдера.

### 1. Замініть залежність

```bash
composer remove djzt/laravel-request-helper djzt/laravel-resource-helper
composer require djzt/laravel-helpers
```

Старі пакети поряд із новим завантажили б ті самі класи двічі, тож приберіть обидва.

### 2. Оновіть провайдери, зареєстровані вручну

Для автопідхоплення робити нічого не треба. Якщо провайдери перелічені руками в
`bootstrap/providers.php` (чи `config/app.php`), замініть обидва рядки одним:

```php
- DJZT\RequestHelper\RequestHelperServiceProvider::class,
- Djzt\ResourceHelper\ResourceHelperServiceProvider::class,
+ DJZT\Helpers\HelpersServiceProvider::class,
```

Обидва початкові провайдери лишилися на місці — їх можна реєструвати окремо, якщо
потрібна лише одна половина пакета.

### 3. Решта без змін

- Імена класів `DJZT\RequestHelper\…` і `Djzt\ResourceHelper\…` не змінилися.
- Конфіги `config/request-helper.php` і `config/resource-helper.php` зберегли назви,
  ключі й теги публікації. Уже опубліковані файли правити не треба.
  `--tag=helpers-config` публікує обидва одразу.
- Аліас фасаду `ResourceHelper` і макрос `typed()` не змінилися.
