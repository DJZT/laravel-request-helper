<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Tests\Feature;

use Carbon\CarbonImmutable;
use DJZT\RequestHelper\Exceptions\InvalidRequestValueException;
use DJZT\RequestHelper\Exceptions\MissingRequestValueException;
use DJZT\RequestHelper\Support\Optional;
use DJZT\RequestHelper\Support\TypedInput;
use DJZT\RequestHelper\Tests\Fixtures\Level;
use DJZT\RequestHelper\Tests\Fixtures\Priority;
use DJZT\RequestHelper\Tests\Fixtures\Status;
use DJZT\RequestHelper\Tests\TestCase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class TypedInputTest extends TestCase
{
    #[Test]
    public function nullable_returns_the_default_when_the_key_is_absent(): void
    {
        $input = $this->input([]);

        $this->assertNull($input->nullableInteger('qty'));
        $this->assertSame(10, $input->nullableInteger('qty', 10));
        $this->assertTrue($input->nullableBoolean('active', true));
        $this->assertSame('n/a', $input->nullableString('name', 'n/a'));
    }

    #[Test]
    public function nullable_keeps_an_explicit_null(): void
    {
        $input = $this->input(['qty' => null]);

        $this->assertNull($input->nullableInteger('qty'));
        $this->assertNull($input->nullableInteger('qty', 10));
    }

    #[Test]
    public function optional_marks_an_absent_key(): void
    {
        $input = $this->input([]);

        $this->assertInstanceOf(Optional::class, $input->optionalInteger('qty'));
        $this->assertInstanceOf(Optional::class, $input->optionalString('name'));
        $this->assertInstanceOf(Optional::class, $input->optionalEnum('status', Status::class));
    }

    #[Test]
    public function optional_returns_null_for_an_explicit_null(): void
    {
        $input = $this->input(['qty' => null]);

        $this->assertNull($input->optionalInteger('qty'));
    }

    #[Test]
    public function optional_returns_the_cast_value_when_present(): void
    {
        $input = $this->input(['qty' => '42']);

        $this->assertSame(42, $input->optionalInteger('qty'));
    }

    #[Test]
    public function required_rejects_an_absent_key(): void
    {
        $this->expectException(MissingRequestValueException::class);

        $this->input([])->requiredInteger('qty');
    }

    #[Test]
    public function required_rejects_an_explicit_null(): void
    {
        $this->expectException(MissingRequestValueException::class);

        $this->input(['qty' => null])->requiredInteger('qty');
    }

    #[Test]
    public function required_returns_the_cast_value(): void
    {
        $this->assertSame(42, $this->input(['qty' => '42'])->requiredInteger('qty'));
    }

    #[Test]
    public function it_reads_dot_notation_keys(): void
    {
        $input = $this->input(['user' => ['age' => '30', 'name' => null]]);

        $this->assertSame(30, $input->requiredInteger('user.age'));
        $this->assertNull($input->optionalString('user.name'));
        $this->assertInstanceOf(Optional::class, $input->optionalString('user.email'));
    }

    #[Test]
    public function it_treats_empty_strings_as_null_by_default(): void
    {
        $input = $this->input(['name' => '']);

        $this->assertNull($input->nullableString('name'));
        $this->assertNull($input->optionalString('name'));
    }

    #[Test]
    public function empty_string_handling_can_be_disabled(): void
    {
        $input = $this->input(['name' => ''])->withEmptyStringAsNull(false);

        $this->assertSame('', $input->nullableString('name'));
    }

    #[Test]
    public function has_reports_presence_including_nulls(): void
    {
        $input = $this->input(['a' => null, 'b' => 1]);

        $this->assertTrue($input->has('a'));
        $this->assertTrue($input->has('b'));
        $this->assertFalse($input->has('c'));
    }

    #[Test]
    #[DataProvider('booleanValues')]
    public function it_casts_booleans(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->input(['flag' => $value])->requiredBoolean('flag'));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function booleanValues(): array
    {
        return [
            'true' => [true, true],
            'false' => [false, false],
            '"1"' => ['1', true],
            '"0"' => ['0', false],
            '"true"' => ['true', true],
            '"yes"' => ['yes', true],
            '"off"' => ['off', false],
            'int 1' => [1, true],
            'int 0' => [0, false],
        ];
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function it_rejects_values_that_do_not_match_the_type(string $method, mixed $value): void
    {
        $this->expectException(InvalidRequestValueException::class);

        $this->input(['value' => $value])->{$method}('value');
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'boolean from word' => ['requiredBoolean', 'maybe'],
            'boolean from 2' => ['requiredBoolean', 2],
            'integer from word' => ['requiredInteger', 'abc'],
            'integer from float string' => ['requiredInteger', '1.5'],
            'integer from bool' => ['requiredInteger', true],
            'float from word' => ['requiredFloat', 'abc'],
            'string from array' => ['requiredString', ['a']],
            'string from bool' => ['requiredString', true],
            'array from string' => ['requiredArray', 'a,b'],
            'date from word' => ['requiredDate', 'not-a-date'],
        ];
    }

    #[Test]
    public function it_casts_numbers(): void
    {
        $input = $this->input(['int' => '42', 'zero' => '0', 'float' => '1.5', 'exp' => '1e3']);

        $this->assertSame(42, $input->requiredInteger('int'));
        $this->assertSame(0, $input->requiredInteger('zero'));
        $this->assertSame(1.5, $input->requiredFloat('float'));
        $this->assertSame(1000.0, $input->requiredFloat('exp'));
        $this->assertSame(42.0, $input->requiredFloat('int'));
    }

    #[Test]
    public function it_casts_strings_from_scalars(): void
    {
        $input = $this->input(['int' => 42, 'float' => 1.5, 'text' => 'hi']);

        $this->assertSame('42', $input->requiredString('int'));
        $this->assertSame('1.5', $input->requiredString('float'));
        $this->assertSame('hi', $input->requiredString('text'));
    }

    #[Test]
    public function it_casts_arrays_and_collections(): void
    {
        $input = $this->input(['tags' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], $input->requiredArray('tags'));
        $this->assertInstanceOf(Collection::class, $input->requiredCollection('tags'));
        $this->assertSame(['a', 'b'], $input->requiredCollection('tags')->all());
        $this->assertInstanceOf(Optional::class, $input->optionalCollection('missing'));
    }

    #[Test]
    public function it_casts_dates(): void
    {
        $input = $this->input([
            'at' => '2026-02-03 10:00:00',
            'custom' => '03/02/2026',
            'stamp' => 1_700_000_000,
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $input->requiredDate('at'));
        $this->assertSame('2026-02-03', $input->requiredDate('at')->toDateString());
        $this->assertSame('2026-02-03', $input->requiredDate('custom', 'd/m/Y')->toDateString());
        $this->assertSame(1_700_000_000, $input->requiredDate('stamp')->getTimestamp());
        $this->assertNull($input->nullableDate('missing'));
    }

    #[Test]
    public function it_rejects_a_date_that_does_not_match_the_given_format(): void
    {
        $this->expectException(InvalidRequestValueException::class);

        $this->input(['at' => '2026-02-03'])->requiredDate('at', 'd/m/Y');
    }

    #[Test]
    public function it_casts_enums(): void
    {
        $input = $this->input(['status' => 'draft', 'priority' => '2']);

        $this->assertSame(Status::Draft, $input->requiredEnum('status', Status::class));
        $this->assertSame(Priority::High, $input->requiredEnum('priority', Priority::class));
        $this->assertSame(Status::Published, $input->nullableEnum('missing', Status::class, Status::Published));
    }

    #[Test]
    public function it_rejects_an_unknown_enum_case(): void
    {
        $this->expectException(InvalidRequestValueException::class);

        $this->input(['status' => 'archived'])->requiredEnum('status', Status::class);
    }

    #[Test]
    public function it_rejects_a_non_numeric_value_for_an_int_backed_enum(): void
    {
        $this->expectException(InvalidRequestValueException::class);

        $this->input(['priority' => 'high'])->requiredEnum('priority', Priority::class);
    }

    #[Test]
    public function it_casts_int_backed_enums_from_real_integers(): void
    {
        $this->assertSame(Priority::Low, $this->input(['priority' => 1])->requiredEnum('priority', Priority::class));
    }

    #[Test]
    public function it_casts_string_backed_enums_from_integers(): void
    {
        $this->assertSame(Level::Two, $this->input(['level' => 2])->requiredEnum('level', Level::class));
        $this->assertSame(Level::Two, $this->input(['level' => '2'])->requiredEnum('level', Level::class));
    }

    #[Test]
    public function it_rejects_a_class_that_is_not_a_backed_enum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->input(['status' => 'draft'])->requiredEnum('status', self::class);
    }

    #[Test]
    public function it_passes_through_an_enum_instance(): void
    {
        $this->assertSame(Status::Published, $this->input(['status' => Status::Published])->requiredEnum('status', Status::class));
    }

    #[Test]
    public function it_reads_from_an_arrayable_source(): void
    {
        $input = TypedInput::for(new Collection(['qty' => '7']));

        $this->assertSame(7, $input->requiredInteger('qty'));
    }
}
