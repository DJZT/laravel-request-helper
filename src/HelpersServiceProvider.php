<?php

declare(strict_types=1);

namespace DJZT\Helpers;

use DJZT\RequestHelper\RequestHelperServiceProvider;
use Djzt\ResourceHelper\ResourceHelperServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * The single entry point of djzt/laravel-helpers.
 *
 * Laravel auto-discovers this provider and it registers every helper the
 * package ships. The individual providers stay usable on their own — register
 * only DJZT\RequestHelper\RequestHelperServiceProvider, for instance, when a
 * project wants the request helpers and nothing else.
 */
final class HelpersServiceProvider extends ServiceProvider
{
    /**
     * The providers making up the package.
     *
     * @var array<int, class-string<ServiceProvider>>
     */
    public const PROVIDERS = [
        RequestHelperServiceProvider::class,
        ResourceHelperServiceProvider::class,
    ];

    public function register(): void
    {
        foreach (self::PROVIDERS as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Each provider publishes its own config under its own tag; this one
        // publishes both at once:
        //
        //     php artisan vendor:publish --tag=helpers-config
        $this->publishes([
            $this->configPath('request-helper.php') => $this->app->configPath('request-helper.php'),
            $this->configPath('resource-helper.php') => $this->app->configPath('resource-helper.php'),
        ], 'helpers-config');
    }

    private function configPath(string $file): string
    {
        return dirname(__DIR__).'/config/'.$file;
    }
}
