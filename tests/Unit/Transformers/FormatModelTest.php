<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class FormatModelTest extends TestCase
{
    private FakeTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FakeTransformer();
    }

    public function test_returns_null_when_collection_is_null(): void
    {
        $this->assertNull($this->transformer->callFormatModel(null, ['id', 'name']));
    }

    public function test_formats_a_single_model_into_a_plain_array(): void
    {
        $model = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice']);

        $this->assertSame(['id' => 1, 'name' => 'Alice'], $this->transformer->callFormatModel($model, ['id', 'name']));
    }

    public function test_formats_a_collection_of_models(): void
    {
        $models = new Collection([
            (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice']),
            (new FakeUser())->forceFill(['id' => 2, 'name' => 'Bob']),
        ]);

        $result = $this->transformer->callFormatModel($models, ['id', 'name']);

        $this->assertCount(2, $result);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame('Bob', $result[1]['name']);
    }

    public function test_formats_a_paginator_like_a_collection(): void
    {
        $items = collect([(new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice'])]);
        $paginator = new LengthAwarePaginator($items, 1, 15);

        $result = $this->transformer->callFormatModel($paginator, ['id', 'name']);

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['name']);
    }

    public function test_skips_scalar_attributes_with_the_hidden_prefix(): void
    {
        $model = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice']);

        $result = $this->transformer->callFormatModel($model, [':name', 'id']);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('name', $result);
    }

    public function test_skips_relation_keys_with_the_hidden_prefix(): void
    {
        $model = (new FakeUser())->forceFill(['id' => 1]);

        $result = $this->transformer->callFormatModel($model, [':address' => ['line1'], 'id']);

        $this->assertArrayNotHasKey('address', $result);
        $this->assertArrayHasKey('id', $result);
    }

    public function test_strips_table_prefix_from_qualified_column_names(): void
    {
        $model = (new FakeUser())->forceFill(['name' => 'Alice']);

        $result = $this->transformer->callFormatModel($model, ['fake_users.name']);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Alice', $result['name']);
    }

    public function test_applies_rename_to_output_key(): void
    {
        $this->transformer->renames = ['*' => ['name' => 'fullName']];
        $model = (new FakeUser())->forceFill(['name' => 'Alice']);

        $result = $this->transformer->callFormatModel($model, ['name']);

        $this->assertArrayHasKey('fullName', $result);
        $this->assertSame('Alice', $result['fullName']);
        $this->assertArrayNotHasKey('name', $result);
    }

    public function test_excludes_guarded_attributes(): void
    {
        $this->transformer->guarded = ['*' => ['name' => 'alwaysGuard']];
        $model = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice']);

        $result = $this->transformer->callFormatModel($model, ['id', 'name']);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('name', $result);
    }

    public function test_resolves_custom_attributes_using_the_prefix(): void
    {
        $this->transformer->customAttributes = ['full_name' => 'getFullName'];
        $model = (new FakeUser())->forceFill(['first_name' => 'Alice', 'last_name' => 'Smith']);

        $result = $this->transformer->callFormatModel($model, ['-full_name']);

        $this->assertArrayHasKey('full_name', $result);
        $this->assertSame('Alice Smith', $result['full_name']);
    }

    public function test_formats_nested_relations_recursively(): void
    {
        $address = (new class extends Model {
            protected $table = 'fake';
            public $timestamps = false;
        })->forceFill(['line1' => '123 Main St', 'city' => 'London']);

        $model = (new FakeUser())->forceFill(['id' => 1]);
        $model->setRelation('address', $address);

        $result = $this->transformer->callFormatModel($model, ['id', 'address' => ['line1', 'city']]);

        $this->assertSame(1, $result['id']);
        $this->assertSame(['line1' => '123 Main St', 'city' => 'London'], $result['address']);
    }
}
