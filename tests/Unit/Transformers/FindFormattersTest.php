<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;

class FindFormattersTest extends TestCase
{
    private FakeTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FakeTransformer();
    }

    public function test_returns_value_unchanged_when_formatters_is_empty(): void
    {
        $this->assertSame('alice', $this->transformer->callFindFormatters('name', 'alice'));
    }

    public function test_applies_global_formatter_from_wildcard_key(): void
    {
        $this->transformer->formatters = ['*' => ['name' => 'toUpper']];

        $this->assertSame('ALICE', $this->transformer->callFindFormatters('name', 'alice'));
    }

    public function test_returns_value_unchanged_when_attribute_not_in_global_formatter_map(): void
    {
        $this->transformer->formatters = ['*' => ['email' => 'toUpper']];

        $this->assertSame('alice', $this->transformer->callFindFormatters('name', 'alice'));
    }

    public function test_applies_path_specific_formatter_using_dot_notation(): void
    {
        $this->transformer->formatters = ['user.score' => ['doubleValue' => []]];

        $this->assertSame(10, $this->transformer->callFindFormatters('score', 5, ['user']));
    }

    public function test_applies_global_formatter_first_then_path_specific_on_the_result(): void
    {
        // global: toUpper turns 'alice' -> 'ALICE'
        // path-specific: addPrefix prepends 'Hello, ' -> 'Hello, ALICE'
        $this->transformer->formatters = [
            '*'         => ['name' => 'toUpper'],
            'user.name' => ['addPrefix' => ['Hello, ']],
        ];

        $this->assertSame('Hello, ALICE', $this->transformer->callFindFormatters('name', 'alice', ['user']));
    }

    public function test_builds_correct_dot_path_for_nested_current_key(): void
    {
        $this->transformer->formatters = ['a.b.score' => ['doubleValue' => []]];

        $this->assertSame(6, $this->transformer->callFindFormatters('score', 3, ['a', 'b']));
    }
}
