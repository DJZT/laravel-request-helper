<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Tests\Fixtures;

use DJZT\RequestHelper\Http\Requests\BaseRequest;

class SampleRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
