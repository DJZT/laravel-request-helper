<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper\Tests;

use DJZT\Helpers\HelpersServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [HelpersServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
    }
}
