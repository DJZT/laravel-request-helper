# Changelog

All notable changes to `djzt/laravel-request-helper` will be documented in this file.

## 0.1.0 - 2026-09-01

Initial release.

- `nullable*`, `optional*` and `required*` accessors for boolean, integer, float,
  string, array, collection, date and backed enum values.
- `Optional` marker separating "key absent" from "value is null".
- `BaseRequest` and the `InteractsWithTypedInput` trait.
- `TypedInput` reader usable on any request, array or `Arrayable`.
- `typed()` macro on `Illuminate\Http\Request`.
- Failures render as regular 422 validation errors.

While the package is on 0.x the public API may still change; minor releases can
carry breaking changes until 1.0.0.
