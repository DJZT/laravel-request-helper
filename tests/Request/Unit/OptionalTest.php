<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Tests\Unit;

use DJZT\RequestHelper\Support\Optional;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OptionalTest extends TestCase
{
    #[Test]
    public function it_recognises_missing_and_present_values(): void
    {
        $this->assertTrue(Optional::isMissing(Optional::create()));
        $this->assertFalse(Optional::isMissing(null));
        $this->assertFalse(Optional::isMissing('draft'));

        $this->assertTrue(Optional::isPresent(null));
        $this->assertTrue(Optional::isPresent(0));
        $this->assertFalse(Optional::isPresent(new Optional));
    }

    #[Test]
    public function it_unwraps_values_with_a_fallback(): void
    {
        $this->assertSame('fallback', Optional::value(Optional::create(), 'fallback'));
        $this->assertNull(Optional::value(Optional::create()));
        $this->assertNull(Optional::value(null, 'fallback'));
        $this->assertSame(0, Optional::value(0, 'fallback'));
    }

    #[Test]
    public function it_filters_absent_values_but_keeps_explicit_nulls(): void
    {
        $filtered = Optional::filter([
            'name' => 'Taylor',
            'email' => null,
            'age' => Optional::create(),
        ]);

        $this->assertSame(['name' => 'Taylor', 'email' => null], $filtered);
    }

    #[Test]
    public function it_serialises_to_null(): void
    {
        $this->assertSame('null', json_encode(Optional::create()));
    }

    #[Test]
    public function it_can_be_used_as_a_constructor_default(): void
    {
        $dto = new class
        {
            public function __construct(
                public readonly string|Optional $name = new Optional,
            ) {}
        };

        $this->assertTrue(Optional::isMissing($dto->name));
    }
}
