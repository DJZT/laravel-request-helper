# Changelog

All notable changes to `djzt/laravel-helpers` will be documented in this file.

## 1.0.0 - 2026-09-01

`djzt/laravel-request-helper` and `djzt/laravel-resource-helper` are merged into a
single package. See [UPGRADE.md](UPGRADE.md) — no class or config key was renamed.

### Added

- One auto-discovered provider, `DJZT\Helpers\HelpersServiceProvider`, registering
  both halves; `RequestHelperServiceProvider` and `ResourceHelperServiceProvider`
  remain registerable on their own.
- A `helpers-config` publish tag that publishes both config files at once.

### Changed

- The Composer package is now `djzt/laravel-helpers`.
- Sources moved to `src/Request/` and `src/Resource/`; the PSR-4 namespaces
  `DJZT\RequestHelper\` and `Djzt\ResourceHelper\` are unchanged.
- The Ukrainian readme is now `README.uk.md` (was `Readme.ua.md`).

### Request helpers

- `nullable*`, `optional*` and `required*` accessors for boolean, integer, float,
  string, array, collection, date and backed enum values.
- `Optional` marker separating "key absent" from "value is null".
- `BaseRequest` and the `InteractsWithTypedInput` trait.
- `TypedInput` reader usable on any request, array or `Arrayable`.
- `typed()` macro on `Illuminate\Http\Request`.
- Failures render as regular 422 validation errors.

### Resource helpers

- Config-driven date formatting: `date()`, `dateTime()`, `time()`, `isoDate()`,
  `timestamp()`, `diffForHumans()`, `dateArray()`, `dates()`.
- Number formatting: `money()`, `number()`, `integer()`, `boolean()`, `percent()`,
  `bytes()`.
- Strings, enums and files: `string()`, `enum()`, `url()`, `translate()`, `mask()`.
- Conditional attributes: `whenLoadedResource()`, `whenCan()`, `whenAuthenticated()`.
- `HelperResource`, `HelperResourceCollection`, the `HasResourceHelpers` trait and
  the `ResourceHelper` facade.

Supports PHP 8.2+ on Laravel 12 and 13.
