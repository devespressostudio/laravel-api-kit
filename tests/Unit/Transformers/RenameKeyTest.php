<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;

class RenameKeyTest extends TestCase
{
    private FakeTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FakeTransformer();
    }

    public function test_returns_attribute_unchanged_when_renames_is_empty(): void
    {
        $this->assertSame('name', $this->transformer->callRenameKey('name'));
    }

    public function test_applies_global_rename_from_wildcard_key(): void
    {
        $this->transformer->renames = ['*' => ['created_at' => 'createdAt']];

        $this->assertSame('createdAt', $this->transformer->callRenameKey('created_at'));
    }

    public function test_returns_attribute_unchanged_when_not_in_global_rename_map(): void
    {
        $this->transformer->renames = ['*' => ['created_at' => 'createdAt']];

        $this->assertSame('name', $this->transformer->callRenameKey('name'));
    }

    public function test_applies_path_specific_rename_using_dot_notation(): void
    {
        $this->transformer->renames = ['address.line1' => 'street'];

        $this->assertSame('street', $this->transformer->callRenameKey('line1', ['address']));
    }

    public function test_global_rename_takes_priority_over_path_specific(): void
    {
        $this->transformer->renames = [
            '*'          => ['name' => 'globalName'],
            'user.name'  => 'pathName',
        ];

        $this->assertSame('globalName', $this->transformer->callRenameKey('name', ['user']));
    }

    public function test_builds_correct_dot_path_from_nested_current_key(): void
    {
        $this->transformer->renames = ['a.b.c' => 'deep'];

        $this->assertSame('deep', $this->transformer->callRenameKey('c', ['a', 'b']));
    }

    public function test_returns_attribute_unchanged_when_no_rename_rule_matches(): void
    {
        $this->transformer->renames = ['user.email' => 'emailAddress'];

        $this->assertSame('name', $this->transformer->callRenameKey('name', ['user']));
    }
}
