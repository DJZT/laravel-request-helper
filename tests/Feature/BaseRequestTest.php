<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Tests\Feature;

use DJZT\RequestHelper\Support\Optional;
use DJZT\RequestHelper\Support\TypedInput;
use DJZT\RequestHelper\Tests\Fixtures\SampleRequest;
use DJZT\RequestHelper\Tests\Fixtures\Status;
use DJZT\RequestHelper\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

final class BaseRequestTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post('/typed', function (SampleRequest $request) {
            $name = $request->optionalString('name');

            return [
                'qty' => $request->requiredInteger('qty'),
                'note' => $request->nullableString('note', 'none'),
                'status' => $request->nullableEnum('status', Status::class)?->value,
                'name' => Optional::isMissing($name) ? 'missing' : $name,
            ];
        });

        $router->post('/typed-macro', fn (Request $request) => [
            'qty' => $request->typed()->requiredInteger('qty'),
        ]);
    }

    #[Test]
    public function it_exposes_typed_accessors_on_a_form_request(): void
    {
        $request = SampleRequest::create('/typed', 'POST', [
            'qty' => '3',
            'note' => null,
        ]);

        $this->assertSame(3, $request->requiredInteger('qty'));
        $this->assertNull($request->nullableString('note', 'none'));
        $this->assertInstanceOf(Optional::class, $request->optionalString('name'));
    }

    #[Test]
    public function it_resolves_typed_values_through_the_container(): void
    {
        $this->postJson('/typed', [
            'qty' => 3,
            'note' => null,
            'status' => 'draft',
        ])->assertOk()->assertExactJson([
            'qty' => 3,
            'note' => null,
            'status' => 'draft',
            'name' => 'missing',
        ]);
    }

    #[Test]
    public function an_uncastable_value_becomes_a_validation_error(): void
    {
        $this->postJson('/typed', ['qty' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qty' => 'The qty field must be an integer.']);
    }

    #[Test]
    public function a_missing_required_value_becomes_a_validation_error(): void
    {
        $this->postJson('/typed', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qty' => 'The qty field is required.']);
    }

    #[Test]
    public function the_request_macro_is_registered(): void
    {
        $this->assertTrue(Request::hasMacro('typed'));

        $this->postJson('/typed-macro', ['qty' => '7'])
            ->assertOk()
            ->assertExactJson(['qty' => 7]);
    }

    #[Test]
    public function the_macro_returns_a_typed_input_reader(): void
    {
        $request = Request::create('/typed-macro', 'POST', ['qty' => '7']);

        $this->assertInstanceOf(TypedInput::class, $request->typed());
        $this->assertSame(7, $request->typed()->requiredInteger('qty'));
    }

    #[Test]
    public function the_package_configuration_is_merged(): void
    {
        $this->assertTrue(config('request-helper.register_macro'));
        $this->assertTrue(config('request-helper.empty_string_as_null'));
    }

    #[Test]
    public function the_typed_input_source_can_be_narrowed_to_validated_data(): void
    {
        $request = new class extends SampleRequest
        {
            protected function typedInput(): TypedInput
            {
                return TypedInput::for(['qty' => 1]);
            }
        };

        $this->assertSame(1, $request->requiredInteger('qty'));
    }
}
