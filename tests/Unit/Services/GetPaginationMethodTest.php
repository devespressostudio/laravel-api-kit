<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Services;

use Devespresso\LaravelApiKit\Tests\Fixtures\Services\FakeFilterService;
use Devespresso\LaravelApiKit\Tests\TestCase;

class GetPaginationMethodTest extends TestCase
{
    private FakeFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FakeFilterService();
    }

    public function test_returns_simple_paginate_when_null_and_config_with_pages_is_false(): void
    {
        config(['devespressoApi.pagination.with_pages' => false]);

        $this->assertSame('simplePaginate', $this->service->callGetPaginationMethod(null));
    }

    public function test_returns_paginate_when_null_and_config_with_pages_is_true(): void
    {
        config(['devespressoApi.pagination.with_pages' => true]);

        $this->assertSame('paginate', $this->service->callGetPaginationMethod(null));
    }

    public function test_returns_get_when_pagination_type_is_none(): void
    {
        $this->assertSame('get', $this->service->callGetPaginationMethod('none'));
    }

    public function test_returns_simple_paginate_when_pagination_type_is_simple(): void
    {
        $this->assertSame('simplePaginate', $this->service->callGetPaginationMethod('simple'));
    }

    public function test_returns_paginate_when_pagination_type_is_full(): void
    {
        $this->assertSame('paginate', $this->service->callGetPaginationMethod('full'));
    }

    public function test_returns_paginate_for_any_unrecognised_non_null_type(): void
    {
        $this->assertSame('paginate', $this->service->callGetPaginationMethod('cursor'));
    }
}
