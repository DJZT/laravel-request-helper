<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Tests;

use DJZT\RequestHelper\RequestHelperServiceProvider;
use DJZT\RequestHelper\Support\TypedInput;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RequestHelperServiceProvider::class];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    protected function input(array $data): TypedInput
    {
        return TypedInput::for($data);
    }
}
