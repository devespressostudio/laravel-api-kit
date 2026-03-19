<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Services;

use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeAdminUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Services\FakeFilterService;
use Devespresso\LaravelApiKit\Tests\TestCase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class FilterPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createFakeUsersTable();
    }

    private function makeService(): FakeFilterService
    {
        return (new FakeFilterService())->setModel(new FakeUser())->setUser(null);
    }

    // -------------------------------------------------------------------------
    // Basic pipeline
    // -------------------------------------------------------------------------

    public function test_filter_returns_paginated_results_by_default(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $result = $this->makeService()->filter();

        // Default is simplePaginate (pagination.with_pages = false)
        $this->assertInstanceOf(Paginator::class, $result);
        $this->assertCount(1, $result->items());
    }

    public function test_filter_returns_collection_when_pagination_type_is_none(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $result = $this->makeService()->setData(['pagination_type' => 'none'])->filter();

        $this->assertInstanceOf(Collection::class, $result);
    }

    // -------------------------------------------------------------------------
    // setConditions
    // -------------------------------------------------------------------------

    public function test_set_conditions_is_called_by_default(): void
    {
        $service = $this->makeService();

        $service->filter();

        $this->assertTrue($service->wasConditionsCalled());
    }

    public function test_set_conditions_is_skipped_after_disable_conditions(): void
    {
        $service = $this->makeService();
        $service->disableConditions();

        $service->filter();

        $this->assertFalse($service->wasConditionsCalled());
    }

    public function test_conditions_callback_filters_results(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        FakeUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        $service = $this->makeService()->setData(['pagination_type' => 'none']);
        $service->setConditionsCallback(fn ($query) => $query->where('name', 'Alice'));

        $result = $service->filter();

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }

    // -------------------------------------------------------------------------
    // Filter method dispatch
    // -------------------------------------------------------------------------

    public function test_data_key_is_dispatched_to_matching_filter_method(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        FakeUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        $service = new class extends FakeFilterService {
            public function name(string $value): void
            {
                $this->query->where('name', $value);
            }
        };

        $result = $service
            ->setModel(new FakeUser())
            ->setUser(null)
            ->setData(['name' => 'Alice', 'pagination_type' => 'none'])
            ->filter();

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }

    public function test_guarded_method_is_not_dispatched_via_data(): void
    {
        $service = new class extends FakeFilterService {
            public bool $nameWasCalled = false;

            public function name(string $value): void
            {
                $this->nameWasCalled = true;
            }
        };

        $service->guardedMethods = ['name'];

        $service
            ->setModel(new FakeUser())
            ->setUser(null)
            ->setData(['name' => 'Alice', 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->nameWasCalled);
    }

    public function test_admin_method_is_skipped_for_non_admin_user(): void
    {
        $service = new class extends FakeFilterService {
            public bool $adminOnlyWasCalled = false;

            public function adminOnly(mixed $value): void
            {
                $this->adminOnlyWasCalled = true;
            }
        };

        $service->adminMethods = ['adminOnly'];

        $service
            ->setModel(new FakeUser())
            ->setUser(null)
            ->setData(['admin_only' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->adminOnlyWasCalled);
    }

    public function test_admin_method_is_executed_for_admin_user(): void
    {
        $service = new class extends FakeFilterService {
            public bool $adminOnlyWasCalled = false;

            public function adminOnly(mixed $value): void
            {
                $this->adminOnlyWasCalled = true;
            }
        };

        $service->adminMethods = ['adminOnly'];

        $service
            ->setModel(new FakeUser())
            ->setUser(new FakeAdminUser())
            ->setData(['admin_only' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertTrue($service->adminOnlyWasCalled);
    }

    // -------------------------------------------------------------------------
    // isDispatchableMethod — all three conditions
    // -------------------------------------------------------------------------

    public function test_non_existent_method_is_not_dispatched(): void
    {
        $service = $this->makeService()
            ->setData(['non_existent_method' => true, 'pagination_type' => 'none']);

        // Should not throw — silently skipped
        $result = $service->filter();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_protected_method_is_not_dispatched_via_data(): void
    {
        $service = new class extends FakeFilterService {
            public bool $protectedWasCalled = false;

            protected function protectedFilter(mixed $value): void
            {
                $this->protectedWasCalled = true;
            }
        };

        $service
            ->setModel(new FakeUser())
            ->setUser(null)
            ->setData(['protected_filter' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->protectedWasCalled);
    }

    public function test_guarded_method_is_not_dispatched_even_if_public_and_exists(): void
    {
        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public function sensitiveMethod(mixed $value): void
            {
                $this->wasCalled = true;
            }
        };

        $service->guardedMethods = ['sensitiveMethod'];

        $service
            ->setModel(new FakeUser())
            ->setUser(null)
            ->setData(['sensitive_method' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->wasCalled);
    }

    // -------------------------------------------------------------------------
    // Auto-apply
    // -------------------------------------------------------------------------

    public function test_auto_apply_filters_always_run_regardless_of_data(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        FakeUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        $service = new class extends FakeFilterService {
            public $autoApply = ['onlyAlice' => true];

            public function onlyAlice(mixed $value): void
            {
                $this->query->where('name', 'Alice');
            }
        };

        $result = $service
            ->setModel(new FakeUser())
            ->setUser(null)
            ->setData(['pagination_type' => 'none'])
            ->filter();

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }

    // -------------------------------------------------------------------------
    // addSelectAndEagerLoad — accessor prefix
    // -------------------------------------------------------------------------

    public function test_accessor_prefixed_columns_are_excluded_from_select(): void
    {
        $this->app['config']->set('devespressoApi.auto_select', true);

        $service = $this->makeService();
        $query = FakeUser::query();
        $service->callAddSelectAndEagerLoad($query, ['id', 'name', '~full_name'], 'fake_users');
        $sql = $query->toSql();

        $this->assertStringContainsString('id', $sql);
        $this->assertStringContainsString('name', $sql);
        $this->assertStringNotContainsString('full_name', $sql);
    }

    // -------------------------------------------------------------------------
    // Per-page
    // -------------------------------------------------------------------------

    public function test_per_page_from_data_is_respected(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            FakeUser::create(['name' => "User {$i}", 'email' => "user{$i}@test.com"]);
        }

        $result = $this->makeService()->setData(['per_page' => 3])->filter();

        $this->assertCount(3, $result->items());
    }
}
