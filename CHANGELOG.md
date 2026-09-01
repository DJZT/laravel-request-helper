# Changelog

All notable changes to `djzt/laravel-request-helper` will be documented in this file.

## 1.0.0

Initial release.

- `nullable*`, `optional*` and `required*` accessors for boolean, integer, float,
  string, array, collection, date and backed enum values.
- `Optional` marker separating "key absent" from "value is null".
- `BaseRequest` and the `InteractsWithTypedInput` trait.
- `TypedInput` reader usable on any request, array or `Arrayable`.
- `typed()` macro on `Illuminate\Http\Request`.
- Failures render as regular 422 validation errors.
