<?php

declare(strict_types=1);

namespace DJZT\Helpers\Tests;

use DJZT\Helpers\HelpersServiceProvider;
use DJZT\RequestHelper\RequestHelperServiceProvider;
use Djzt\ResourceHelper\Facades\ResourceHelper;
use Djzt\ResourceHelper\ResourceHelperServiceProvider;
use Djzt\ResourceHelper\Support\Formatter;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;

final class HelpersServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_both_halves_of_the_package(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(RequestHelperServiceProvider::class));
        $this->assertTrue($this->app->providerIsLoaded(ResourceHelperServiceProvider::class));
    }

    #[Test]
    public function it_merges_both_config_files(): void
    {
        $this->assertTrue(config('request-helper.register_macro'));
        $this->assertSame('Y-m-d', config('resource-helper.formats.date'));
    }

    #[Test]
    public function it_wires_up_the_request_macro_and_the_resource_formatter(): void
    {
        $this->assertTrue(Request::hasMacro('typed'));
        $this->assertSame(3, Request::create('/', 'GET', ['page' => '3'])->typed()->requiredInteger('page'));

        $this->assertInstanceOf(Formatter::class, $this->app->make(Formatter::class));
        $this->assertSame('2026-09-01', ResourceHelper::date('2026-09-01 13:45:07'));
    }

    #[Test]
    public function it_publishes_both_config_files_under_one_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(HelpersServiceProvider::class, 'helpers-config');

        $this->assertSame(
            ['request-helper.php', 'resource-helper.php'],
            collect($paths)->map(static fn (string $target): string => basename($target))->sort()->values()->all(),
        );
    }
}
