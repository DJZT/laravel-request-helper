<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Http\Requests;

use DJZT\RequestHelper\Concerns\InteractsWithTypedInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Drop-in FormRequest base class exposing the typed accessors.
 *
 * Extend it instead of FormRequest, or pull InteractsWithTypedInput into an
 * existing base request of your own.
 */
abstract class BaseRequest extends FormRequest
{
    use InteractsWithTypedInput;

    /**
     * Authorization is delegated to policies/middleware by default.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
