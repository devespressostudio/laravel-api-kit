<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Services;

use Devespresso\LaravelApiKit\Tests\Fixtures\Services\FakeFilterService;
use Devespresso\LaravelApiKit\Tests\TestCase;

class GetBaseGuardedMethodsTest extends TestCase
{
    private FakeFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FakeFilterService();
    }

    public function test_returns_an_array(): void
    {
        $this->assertIsArray($this->service->callGetBaseGuardedMethods());
    }

    public function test_does_not_include_sort(): void
    {
        $this->assertNotContains('sort', $this->service->callGetBaseGuardedMethods());
    }

    public function test_does_not_include_search(): void
    {
        $this->assertNotContains('search', $this->service->callGetBaseGuardedMethods());
    }

    public function test_includes_core_base_class_methods(): void
    {
        $guarded = $this->service->callGetBaseGuardedMethods();

        foreach (['filter', 'setData', 'setUser', 'setModel', 'setQuery', 'setFilters', 'setExtras', 'disableConditions', 'applySort', 'getPaginationMethod'] as $method) {
            $this->assertContains($method, $guarded, "Expected '{$method}' to be guarded.");
        }
    }

    public function test_does_not_include_methods_from_the_subclass(): void
    {
        // FakeFilterService adds callGetBaseGuardedMethods, wasConditionsCalled, etc.
        // These should NOT appear because reflection is scoped to BaseFilterService::class only.
        $guarded = $this->service->callGetBaseGuardedMethods();

        $this->assertNotContains('callGetBaseGuardedMethods', $guarded);
        $this->assertNotContains('wasConditionsCalled', $guarded);
    }
}
