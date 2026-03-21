<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Services;

use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeAdminUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Services\FakeFilterService;
use Devespresso\LaravelApiKit\Tests\Fixtures\Services\FakeNullRoleResolver;
use Devespresso\LaravelApiKit\Tests\Fixtures\Services\FakeRoleResolver;
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

    public function test_role_method_is_skipped_when_no_resolver_configured(): void
    {
        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public $roleMethods = ['admin' => ['adminOnly']];

            public function adminOnly(mixed $value): void
            {
                $this->wasCalled = true;
            }
        };

        $service
            ->setModel(new FakeUser())
            ->setUser(new FakeAdminUser())
            ->setData(['admin_only' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->wasCalled);
    }

    public function test_role_method_is_skipped_when_resolver_returns_null(): void
    {
        $this->app['config']->set('devespressoApi.roles', ['moderator', 'editor', 'admin']);
        $this->app['config']->set('devespressoApi.role_resolver', FakeNullRoleResolver::class);

        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public $roleMethods = ['admin' => ['adminOnly']];

            public function adminOnly(mixed $value): void
            {
                $this->wasCalled = true;
            }
        };

        $service
            ->setModel(new FakeUser())
            ->setUser(new FakeAdminUser())
            ->setData(['admin_only' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->wasCalled);
    }

    public function test_role_method_is_skipped_for_user_without_matching_role(): void
    {
        $this->app['config']->set('devespressoApi.roles', ['moderator', 'editor', 'admin']);
        $this->app['config']->set('devespressoApi.role_resolver', FakeRoleResolver::class);

        $moderator       = new FakeAdminUser();
        $moderator->role = 'moderator';

        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public $roleMethods = ['admin' => ['adminOnly']];

            public function adminOnly(mixed $value): void
            {
                $this->wasCalled = true;
            }
        };

        $service
            ->setModel(new FakeUser())
            ->setUser($moderator)
            ->setData(['admin_only' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->wasCalled);
    }

    public function test_role_method_is_executed_for_user_with_matching_role(): void
    {
        $this->app['config']->set('devespressoApi.roles', ['moderator', 'editor', 'admin']);
        $this->app['config']->set('devespressoApi.role_resolver', FakeRoleResolver::class);

        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public $roleMethods = ['admin' => ['adminOnly']];

            public function adminOnly(mixed $value): void
            {
                $this->wasCalled = true;
            }
        };

        $service
            ->setModel(new FakeUser())
            ->setUser(new FakeAdminUser())
            ->setData(['admin_only' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertTrue($service->wasCalled);
    }

    public function test_higher_role_inherits_lower_role_methods(): void
    {
        $this->app['config']->set('devespressoApi.roles', ['moderator', 'editor', 'admin']);
        $this->app['config']->set('devespressoApi.role_resolver', FakeRoleResolver::class);

        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public $roleMethods = ['moderator' => ['includeArchived']];

            public function includeArchived(mixed $value): void
            {
                $this->wasCalled = true;
            }
        };

        // admin is higher than moderator — should inherit moderator methods
        $service
            ->setModel(new FakeUser())
            ->setUser(new FakeAdminUser())
            ->setData(['include_archived' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertTrue($service->wasCalled);
    }

    public function test_lower_role_cannot_trigger_higher_role_methods(): void
    {
        $this->app['config']->set('devespressoApi.roles', ['moderator', 'editor', 'admin']);
        $this->app['config']->set('devespressoApi.role_resolver', FakeRoleResolver::class);

        $moderator       = new FakeAdminUser();
        $moderator->role = 'moderator';

        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public $roleMethods = ['admin' => ['adminOnly']];

            public function adminOnly(mixed $value): void
            {
                $this->wasCalled = true;
            }
        };

        $service
            ->setModel(new FakeUser())
            ->setUser($moderator)
            ->setData(['admin_only' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->wasCalled);
    }

    public function test_numeric_roles_higher_value_inherits_lower_methods(): void
    {
        $this->app['config']->set('devespressoApi.numeric_roles', true);
        $this->app['config']->set('devespressoApi.role_resolver', FakeRoleResolver::class);

        $user       = new FakeAdminUser();
        $user->role = 3;

        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public $roleMethods = [
                1 => ['levelOneMethod'],
                2 => ['levelTwoMethod'],
                3 => ['levelThreeMethod'],
            ];

            public function levelOneMethod(mixed $value): void
            {
                $this->wasCalled = true;
            }

            public function levelTwoMethod(mixed $value): void {}
            public function levelThreeMethod(mixed $value): void {}
        };

        $service
            ->setModel(new FakeUser())
            ->setUser($user)
            ->setData(['level_one_method' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertTrue($service->wasCalled);
    }

    public function test_numeric_roles_lower_value_cannot_trigger_higher_methods(): void
    {
        $this->app['config']->set('devespressoApi.numeric_roles', true);
        $this->app['config']->set('devespressoApi.role_resolver', FakeRoleResolver::class);

        $user       = new FakeAdminUser();
        $user->role = 1;

        $service = new class extends FakeFilterService {
            public bool $wasCalled = false;

            public $roleMethods = [
                1 => ['levelOneMethod'],
                3 => ['levelThreeMethod'],
            ];

            public function levelOneMethod(mixed $value): void {}

            public function levelThreeMethod(mixed $value): void
            {
                $this->wasCalled = true;
            }
        };

        $service
            ->setModel(new FakeUser())
            ->setUser($user)
            ->setData(['level_three_method' => true, 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->wasCalled);
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
    // Explicit filtering
    // -------------------------------------------------------------------------

    public function test_explicit_filter_allows_listed_key(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        FakeUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        $this->app['config']->set('devespressoApi.enable_explicit_filtering', true);

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
            ->setExplicitFilters(['name'])
            ->filter();

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }

    public function test_explicit_filter_blocks_unlisted_key(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        FakeUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        $this->app['config']->set('devespressoApi.enable_explicit_filtering', true);

        $service = new class extends FakeFilterService {
            public bool $statusWasCalled = false;

            public function status(string $value): void
            {
                $this->statusWasCalled = true;
                $this->query->where('status', $value);
            }
        };

        $result = $service
            ->setModel(new FakeUser())
            ->setUser(null)
            ->setData(['status' => 'active', 'pagination_type' => 'none'])
            ->setExplicitFilters(['name']) // 'status' not listed
            ->filter();

        $this->assertFalse($service->statusWasCalled);
        $this->assertCount(2, $result); // both records returned — filter was not applied
    }

    public function test_explicit_filter_does_not_restrict_when_config_is_disabled(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        FakeUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        // config is false (default) — explicit list has no effect
        $this->app['config']->set('devespressoApi.enable_explicit_filtering', false);

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
            ->setExplicitFilters([]) // empty — would block all if config were on
            ->filter();

        $this->assertCount(1, $result); // filter still ran
    }

    public function test_sort_is_always_exempt_from_explicit_filter(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        FakeUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        $this->app['config']->set('devespressoApi.enable_explicit_filtering', true);

        $result = $this->makeService()
            ->setData(['sort' => 'id,asc', 'pagination_type' => 'none'])
            ->setExplicitFilters([]) // sort not listed — should still run
            ->filter();

        $this->assertSame('Alice', $result->first()->name); // Alice was inserted first (id=1)
    }

    public function test_explicit_filter_blocks_all_when_config_on_and_no_list_provided(): void
    {
        FakeUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        FakeUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        $this->app['config']->set('devespressoApi.enable_explicit_filtering', true);

        $service = new class extends FakeFilterService {
            public bool $nameWasCalled = false;

            public function name(string $value): void
            {
                $this->nameWasCalled = true;
                $this->query->where('name', $value);
            }
        };

        // No setExplicitFilters() call — treated as empty list, all filters blocked
        $result = $service
            ->setModel(new FakeUser())
            ->setUser(null)
            ->setData(['name' => 'Alice', 'pagination_type' => 'none'])
            ->filter();

        $this->assertFalse($service->nameWasCalled);
        $this->assertCount(2, $result); // filter did not run, both records returned
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
