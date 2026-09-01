<?php

declare(strict_types=1);

namespace DJZT\RequestHelper;

use DJZT\RequestHelper\Support\TypedInput;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class RequestHelperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'request-helper');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('request-helper.php'),
            ], 'request-helper-config');
        }

        if ($this->app['config']->get('request-helper.register_macro', true)) {
            $this->registerMacro();
        }
    }

    private function registerMacro(): void
    {
        if (Request::hasMacro('typed')) {
            return;
        }

        Request::macro('typed', function (): TypedInput {
            /** @var Request $this */
            return TypedInput::for($this);
        });
    }

    private function configPath(): string
    {
        return __DIR__.'/../config/request-helper.php';
    }
}
