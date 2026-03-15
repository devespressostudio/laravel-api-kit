<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Services;

use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Services\FakeFilterService;
use Devespresso\LaravelApiKit\Tests\TestCase;

class SortTest extends TestCase
{
    private FakeFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FakeFilterService();
        // Use a real query builder (no table needed for building, only for executing)
        $this->service->setQuery(FakeUser::query());
    }

    private function getOrders(): array
    {
        return $this->service->getQuery()->getQuery()->orders ?? [];
    }

    // -------------------------------------------------------------------------
    // sort()
    // -------------------------------------------------------------------------

    public function test_sort_returns_self_for_fluent_chaining(): void
    {
        $result = $this->service->sort(['id,desc']);

        $this->assertSame($this->service, $result);
    }

    public function test_sort_accepts_a_single_string_and_applies_it(): void
    {
        $this->service->sort('id,asc');

        $this->assertSame([['column' => 'id', 'direction' => 'asc']], $this->getOrders());
    }

    public function test_sort_applies_each_value_when_given_an_array(): void
    {
        $this->service->sort(['id,asc', 'created_at,desc']);

        $columns = array_column($this->getOrders(), 'column');
        $this->assertContains('id', $columns);
        $this->assertContains('created_at', $columns);
    }

    // -------------------------------------------------------------------------
    // applySort()
    // -------------------------------------------------------------------------

    public function test_apply_sort_applies_valid_column_with_asc_direction(): void
    {
        $this->service->applySort('created_at,asc');

        $this->assertSame([['column' => 'created_at', 'direction' => 'asc']], $this->getOrders());
    }

    public function test_apply_sort_defaults_direction_to_desc_when_omitted(): void
    {
        $this->service->applySort('id');

        $this->assertSame('desc', $this->getOrders()[0]['direction']);
    }

    public function test_apply_sort_coerces_invalid_direction_to_desc(): void
    {
        $this->service->applySort('id,invalid');

        $this->assertSame('desc', $this->getOrders()[0]['direction']);
    }

    public function test_apply_sort_falls_back_to_default_column_for_unknown_column(): void
    {
        $this->service->applySort('nonexistent_column,asc');

        // Falls back to defaultSortingColumn = ['id,desc']
        $this->assertSame('id', $this->getOrders()[0]['column']);
        $this->assertSame('desc', $this->getOrders()[0]['direction']);
    }

    public function test_apply_sort_uses_column_directly_when_has_been_renamed_is_true(): void
    {
        // Even if the column is not in sortColumns, hasBeenRenamed=true bypasses the fallback
        $this->service->applySort('renamed_column,asc', true);

        $this->assertSame('renamed_column', $this->getOrders()[0]['column']);
    }

    public function test_apply_sort_resolves_custom_sort_column_alias(): void
    {
        $this->service->customSortColumns = ['alias' => 'users.name'];

        $this->service->applySort('alias,asc');

        // Should redirect to 'users.name' with hasBeenRenamed=true
        $this->assertSame('users.name', $this->getOrders()[0]['column']);
    }

    // -------------------------------------------------------------------------
    // rawSort
    // -------------------------------------------------------------------------

    public function test_apply_sort_calls_raw_sort_method_and_applies_order_by_raw(): void
    {
        $service = new class extends FakeFilterService {
            public function sortByStatus(): string
            {
                return "FIELD(status, 'active', 'pending')";
            }
        };

        $service->rawSort = ['status_order' => 'sortByStatus'];
        $service->setQuery(FakeUser::query());

        $service->applySort('status_order,asc');

        $orders = $service->getQuery()->getQuery()->orders ?? [];
        $this->assertSame("FIELD(status, 'active', 'pending') asc", $orders[0]['sql']);
    }

    public function test_apply_sort_raw_sort_defaults_direction_to_desc(): void
    {
        $service = new class extends FakeFilterService {
            public function sortByStatus(): string
            {
                return "FIELD(status, 'active', 'pending')";
            }
        };

        $service->rawSort = ['status_order' => 'sortByStatus'];
        $service->setQuery(FakeUser::query());

        $service->applySort('status_order');

        $orders = $service->getQuery()->getQuery()->orders ?? [];
        $this->assertSame("FIELD(status, 'active', 'pending') desc", $orders[0]['sql']);
    }

    public function test_apply_sort_raw_sort_silently_skips_missing_method(): void
    {
        $this->service->rawSort = ['status_order' => 'nonExistentMethod'];

        // Should not throw
        $this->service->applySort('status_order,asc');

        $this->assertEmpty($this->getOrders());
    }
}
