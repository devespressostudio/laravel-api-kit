<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;

class ApplyDefaultValueTest extends TestCase
{
    private FakeTransformer $transformer;
    private FakeUser $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FakeTransformer();
        $this->model = new FakeUser();
    }

    public function test_returns_value_immediately_when_it_is_not_null(): void
    {
        $this->assertSame('existing', $this->transformer->applyDefaultValue('existing', 'status', [], $this->model));
    }

    public function test_returns_global_scalar_default_when_value_is_null(): void
    {
        $this->transformer->defaults = ['*' => ['status' => 'active']];

        $this->assertSame('active', $this->transformer->applyDefaultValue(null, 'status', [], $this->model));
    }

    public function test_calls_global_default_method_when_default_is_a_method_name(): void
    {
        $this->transformer->defaults = ['*' => ['status' => 'getDefaultStatus']];

        $this->assertSame('active', $this->transformer->applyDefaultValue(null, 'status', [], $this->model));
    }

    public function test_returns_path_specific_scalar_default_when_no_global_match(): void
    {
        $this->transformer->defaults = ['user.status' => 'pending'];

        $this->assertSame('pending', $this->transformer->applyDefaultValue(null, 'status', ['user'], $this->model));
    }

    public function test_calls_path_specific_default_method_when_default_is_a_method_name(): void
    {
        $this->transformer->defaults = ['user.status' => 'getDefaultStatus'];

        $this->assertSame('active', $this->transformer->applyDefaultValue(null, 'status', ['user'], $this->model));
    }

    public function test_returns_null_when_no_default_rule_matches(): void
    {
        $this->transformer->defaults = ['*' => ['email' => 'n/a']];

        $this->assertNull($this->transformer->applyDefaultValue(null, 'status', [], $this->model));
    }

    public function test_global_default_does_not_fire_when_value_is_not_null(): void
    {
        $this->transformer->defaults = ['*' => ['status' => 'active']];

        $this->assertSame('custom', $this->transformer->applyDefaultValue('custom', 'status', [], $this->model));
    }
}
