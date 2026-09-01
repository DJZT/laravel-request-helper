<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Support;

use Illuminate\Container\Container;
use Throwable;

/**
 * Reads package configuration without ever hard-failing when the framework is
 * not booted (unit tests, queue workers running outside the HTTP kernel, ...).
 *
 * @internal
 */
final class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $container = Container::getInstance();

            if (! $container->bound('config')) {
                return $default;
            }

            return $container->make('config')->get('request-helper.'.$key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
