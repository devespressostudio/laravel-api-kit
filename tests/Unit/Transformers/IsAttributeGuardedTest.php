<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;

class IsAttributeGuardedTest extends TestCase
{
    private FakeTransformer $transformer;
    private FakeUser $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FakeTransformer();
        $this->model = new FakeUser();
    }

    public function test_returns_false_when_guarded_is_empty(): void
    {
        $this->assertFalse($this->transformer->callIsAttributeGuarded('secret', [], $this->model));
    }

    public function test_returns_true_when_global_guard_method_returns_true(): void
    {
        $this->transformer->guarded = ['*' => ['secret' => 'alwaysGuard']];

        $this->assertTrue($this->transformer->callIsAttributeGuarded('secret', [], $this->model));
    }

    public function test_returns_false_when_global_guard_method_returns_false(): void
    {
        $this->transformer->guarded = ['*' => ['secret' => 'neverGuard']];

        $this->assertFalse($this->transformer->callIsAttributeGuarded('secret', [], $this->model));
    }

    public function test_returns_false_when_attribute_is_not_in_global_guard_map(): void
    {
        $this->transformer->guarded = ['*' => ['other' => 'alwaysGuard']];

        $this->assertFalse($this->transformer->callIsAttributeGuarded('secret', [], $this->model));
    }

    public function test_applies_path_specific_guard_using_dot_notation(): void
    {
        $this->transformer->guarded = ['user.salary' => 'alwaysGuard'];

        $this->assertTrue($this->transformer->callIsAttributeGuarded('salary', ['user'], $this->model));
    }

    public function test_global_guard_takes_priority_over_path_specific(): void
    {
        // global says always guard (true), path-specific says never guard (false)
        // global fires first and returns, so path-specific is never reached
        $this->transformer->guarded = [
            '*'           => ['secret' => 'alwaysGuard'],
            'user.secret' => 'neverGuard',
        ];

        $this->assertTrue($this->transformer->callIsAttributeGuarded('secret', ['user'], $this->model));
    }

    public function test_returns_false_when_guard_method_does_not_exist_on_transformer(): void
    {
        $this->transformer->guarded = ['*' => ['secret' => 'nonExistentMethod']];

        $this->assertFalse($this->transformer->callIsAttributeGuarded('secret', [], $this->model));
    }
}
