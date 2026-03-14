<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;

class CheckCustomAttributeTest extends TestCase
{
    private FakeTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FakeTransformer();
    }

    public function test_reads_attribute_directly_from_model_when_not_custom(): void
    {
        $model = (new FakeUser())->forceFill(['name' => 'Alice']);

        $this->assertSame('Alice', $this->transformer->callCheckForCustomAttributeAndGetValue('name', $model, false));
    }

    public function test_calls_mapped_method_when_is_custom_attribute_is_true(): void
    {
        $this->transformer->customAttributes = ['full_name' => 'getFullName'];
        $model = (new FakeUser())->forceFill(['first_name' => 'Alice', 'last_name' => 'Smith']);

        $this->assertSame('Alice Smith', $this->transformer->callCheckForCustomAttributeAndGetValue('full_name', $model, true));
    }

    public function test_falls_back_to_model_property_when_attribute_is_not_in_custom_map(): void
    {
        $this->transformer->customAttributes = [];
        $model = (new FakeUser())->forceFill(['name' => 'Alice']);

        $this->assertSame('Alice', $this->transformer->callCheckForCustomAttributeAndGetValue('name', $model, true));
    }
}
