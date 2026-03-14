<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;

class RunFormattersTest extends TestCase
{
    private FakeTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FakeTransformer();
    }

    public function test_calls_single_method_when_formatters_is_a_string(): void
    {
        $this->assertSame('ALICE', $this->transformer->callRunFormatters('alice', 'toUpper'));
    }

    public function test_calls_method_with_extra_params_when_formatters_is_method_to_array_map(): void
    {
        $this->assertSame('Hello, world', $this->transformer->callRunFormatters('world', ['addPrefix' => ['Hello, ']]));
    }

    public function test_treats_indexed_array_value_as_the_method_name(): void
    {
        // [0 => 'toUpper'] — $params is 'toUpper', not an array, so $this->toUpper($value) is called
        $this->assertSame('ALICE', $this->transformer->callRunFormatters('alice', [0 => 'toUpper']));
    }

    public function test_chains_multiple_formatters_passing_result_to_the_next(): void
    {
        // addPrefix first, then toUpper on the result
        $result = $this->transformer->callRunFormatters('world', [
            'addPrefix' => ['Hello, '],
            0           => 'toUpper',
        ]);

        $this->assertSame('HELLO, WORLD', $result);
    }
}
