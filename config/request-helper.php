<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Request Macro
    |--------------------------------------------------------------------------
    |
    | Registers a `typed()` macro on Illuminate\Http\Request, so the accessors
    | are reachable from any request without extending BaseRequest:
    |
    |     $request->typed()->requiredInteger('page');
    |
    */

    'register_macro' => true,

    /*
    |--------------------------------------------------------------------------
    | Empty Strings As Null
    |--------------------------------------------------------------------------
    |
    | Query strings cannot express null: "?name=" arrives as an empty string.
    | With this enabled (matching Laravel's ConvertEmptyStringsToNull middleware)
    | an empty string is read as null, so nullableString('name') returns null
    | instead of "".
    |
    */

    'empty_string_as_null' => true,

];
