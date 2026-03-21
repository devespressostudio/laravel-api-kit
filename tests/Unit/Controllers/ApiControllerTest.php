<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Controllers;

use Devespresso\LaravelApiKit\Controllers\ApiController;
use Devespresso\LaravelApiKit\Tests\Fixtures\Controllers\FakeApiController;
use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;

class ApiControllerTest extends TestCase
{
    private FakeApiController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FakeApiController();
    }

    public function test_respond_returns_correct_default_structure(): void
    {
        $response = $this->controller->respondWithDefaults();

        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'code' => 200,
            'status' => 'success',
            'message' => 'OK',
        ], $data);
    }

    public function test_set_raw_data_with_default_key_returns_correct_structure(): void
    {
        $response = $this->controller->respondWithRawData(['foo' => 'bar']);

        $data = $response->getData(true);

        $this->assertSame([
            'code' => 200,
            'status' => 'success',
            'message' => 'OK',
            'data' => ['foo' => 'bar'],
        ], $data);
    }

    public function test_set_raw_data_with_custom_key_returns_correct_structure(): void
    {
        $response = $this->controller->respondWithRawData(['total' => 100], 'stats');

        $data = $response->getData(true);

        $this->assertSame([
            'code' => 200,
            'status' => 'success',
            'message' => 'OK',
            'stats' => ['total' => 100],
        ], $data);
    }

    public function test_set_meta_returns_correct_structure(): void
    {
        $response = $this->controller->respondWithMeta([
            'permissions' => ['edit', 'delete'],
            'roles' => ['admin'],
        ]);

        $data = $response->getData(true);

        $this->assertSame([
            'code' => 200,
            'status' => 'success',
            'message' => 'OK',
            'meta' => [
                'permissions' => ['edit', 'delete'],
                'roles' => ['admin'],
            ],
        ], $data);
    }

    public function test_set_meta_with_empty_array_excludes_meta_from_structure(): void
    {
        $response = $this->controller->respondWithMeta([]);

        $data = $response->getData(true);

        $this->assertSame([
            'code' => 200,
            'status' => 'success',
            'message' => 'OK',
        ], $data);
    }

    public function test_add_meta_returns_correct_structure(): void
    {
        $response = $this->controller->respondWithAddMeta('permissions', ['edit']);

        $data = $response->getData(true);

        $this->assertSame([
            'code' => 200,
            'status' => 'success',
            'message' => 'OK',
            'meta' => [
                'permissions' => ['edit'],
            ],
        ], $data);
    }

    public function test_add_meta_chained_returns_correct_structure(): void
    {
        $response = $this->controller->respondWithMultipleMeta(
            ['permissions', ['edit', 'delete']],
            ['roles', ['admin']],
        );

        $data = $response->getData(true);

        $this->assertSame([
            'code' => 200,
            'status' => 'success',
            'message' => 'OK',
            'meta' => [
                'permissions' => ['edit', 'delete'],
                'roles' => ['admin'],
            ],
        ], $data);
    }

    public function test_respond_created_returns_correct_structure(): void
    {
        $response = $this->controller->respondWithCreated();

        $data = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([
            'code' => 201,
            'status' => 'success',
            'message' => 'Created',
        ], $data);
    }

    public function test_respond_no_content_returns_correct_structure(): void
    {
        $response = $this->controller->respondWithNoContent();

        $data = $response->getData(true);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame([
            'code' => 204,
            'status' => 'success',
            'message' => 'No Content',
        ], $data);
    }

    public function test_error_code_returns_correct_structure(): void
    {
        $response = $this->controller->respondWithCode(422, 'Validation failed');

        $data = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'code' => 422,
            'status' => 'error',
            'message' => 'Validation failed',
        ], $data);
    }

    public function test_raw_data_and_meta_coexist_with_correct_structure(): void
    {
        $controller = new FakeApiController();
        $controller->respondWithAddMeta('permissions', ['edit']);
        $response = $controller->respondWithRawData(['user' => 'John']);

        $data = $response->getData(true);

        $this->assertSame([
            'code' => 200,
            'status' => 'success',
            'message' => 'OK',
            'meta' => [
                'permissions' => ['edit'],
            ],
            'data' => ['user' => 'John'],
        ], $data);
    }

    public function test_append_to_data_creates_array_on_first_call(): void
    {
        $controller = new FakeApiController();
        $controller->respondWithAppendTo(['id' => 1]);
        $response = $controller->respondWithDefaults();

        $data = $response->getData(true);

        $this->assertSame(['id' => 1], $data['data']);
    }

    public function test_append_to_data_merges_on_subsequent_calls(): void
    {
        $controller = new FakeApiController();
        $controller->respondWithAppendTo(['permissions' => ['update' => false, 'delete' => true]]);
        $controller->respondWithAppendTo(['meta' => ['total' => 5]]);
        $response = $controller->respondWithDefaults();

        $data = $response->getData(true);

        $this->assertSame([
            'permissions' => ['update' => false, 'delete' => true],
            'meta'        => ['total' => 5],
        ], $data['data']);
    }

    public function test_append_to_data_supports_custom_key(): void
    {
        $controller = new FakeApiController();
        $controller->respondWithAppendTo(['count' => 10], 'stats');
        $controller->respondWithAppendTo(['average' => 2.5], 'stats');
        $response = $controller->respondWithDefaults();

        $data = $response->getData(true);

        $this->assertSame(['count' => 10, 'average' => 2.5], $data['stats']);
    }

    public function test_append_to_data_and_set_raw_data_coexist_on_different_keys(): void
    {
        $controller = new FakeApiController();
        $controller->respondWithAppendTo(['id' => 1], 'items');
        $controller->respondWithRawData(['total' => 1], 'meta');
        $response = $controller->respondWithDefaults();

        $data = $response->getData(true);

        $this->assertSame(['id' => 1], $data['items']);
        $this->assertSame(['total' => 1], $data['meta']);
    }

    public function test_respond_created_with_raw_data_returns_correct_structure(): void
    {
        $controller = new FakeApiController();
        $controller->respondWithRawData(['id' => 1, 'name' => 'John']);
        $response = $controller->respondWithCreated();

        $data = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([
            'code' => 201,
            'status' => 'success',
            'message' => 'Created',
            'data' => ['id' => 1, 'name' => 'John'],
        ], $data);
    }

    // -------------------------------------------------------------------------
    // $version — populated from transformer after setData()
    // -------------------------------------------------------------------------

    public function test_version_is_set_on_controller_after_set_data(): void
    {
        $this->app['config']->set('devespressoApi.versioning.enabled', true);
        $this->app['config']->set('devespressoApi.versioning.driver', 'header');
        $this->app['config']->set('devespressoApi.versioning.versions', ['v2', 'v3']);
        $this->app['request']->headers->set('X-Api-Version', 'v2');

        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['avatar']]];
            }
        };
        $transformer->formats = ['*' => ['id', 'name']];

        $controller = new class ($transformer) extends FakeApiController {
            protected bool $autoResolveTransformer = true;

            public function __construct(private FakeTransformer $fakeTransformer) {}

            protected function resolveTransformer(): \Devespresso\LaravelApiKit\Transformers\BaseTransformer
            {
                return $this->fakeTransformer;
            }
        };

        $model = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice']);
        $controller->callSetData($model);

        $this->assertSame('v2', $controller->getVersion());
    }

    public function test_version_is_null_when_versioning_disabled(): void
    {
        $transformer = new FakeTransformer();
        $transformer->formats = ['*' => ['id']];

        $controller = new class ($transformer) extends FakeApiController {
            protected bool $autoResolveTransformer = true;

            public function __construct(private FakeTransformer $fakeTransformer) {}

            protected function resolveTransformer(): \Devespresso\LaravelApiKit\Transformers\BaseTransformer
            {
                return $this->fakeTransformer;
            }
        };

        $model = (new FakeUser())->forceFill(['id' => 1]);
        $controller->callSetData($model);

        $this->assertNull($controller->getVersion());
    }
}
