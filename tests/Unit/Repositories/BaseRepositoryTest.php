<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Repositories;

use Devespresso\LaravelApiKit\Tests\Fixtures\Repositories\FakeRepository;
use Devespresso\LaravelApiKit\Tests\TestCase;

class BaseRepositoryTest extends TestCase
{
    private FakeRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFakeUsersTable();
        $this->repo = new FakeRepository();
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_returns_model_by_id(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $found = $this->repo->get($user->id);

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_get_returns_null_for_missing_id(): void
    {
        $this->assertNull($this->repo->get(999));
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function test_create_persists_the_model(): void
    {
        $model = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertDatabaseHas('fake_users', ['email' => 'alice@example.com']);
        $this->assertNotNull($model->id);
    }

    public function test_create_calls_before_create_then_after_created_in_order(): void
    {
        $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertSame(['beforeCreate', 'afterCreated'], $this->repo->called);
    }

    public function test_before_create_can_modify_attributes_by_reference(): void
    {
        $repo = new class extends FakeRepository {
            protected function beforeCreate(array &$attributes = []): void
            {
                parent::beforeCreate($attributes);
                $attributes['name'] = 'Modified';
            }
        };

        $model = $repo->create(['name' => 'Original', 'email' => 'mod@example.com']);

        $this->assertSame('Modified', $model->name);
    }

    // -------------------------------------------------------------------------
    // update()
    // -------------------------------------------------------------------------

    public function test_update_persists_changes(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->repo->update($user, ['name' => 'Bob']);

        $this->assertDatabaseHas('fake_users', ['id' => $user->id, 'name' => 'Bob']);
    }

    public function test_update_calls_before_update_then_after_updated_in_order(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->repo->called = [];

        $this->repo->update($user, ['name' => 'Bob']);

        $this->assertSame(['beforeUpdate', 'afterUpdated'], $this->repo->called);
    }

    public function test_update_accepts_a_raw_id(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->repo->update($user->id, ['name' => 'Bob']);

        $this->assertDatabaseHas('fake_users', ['id' => $user->id, 'name' => 'Bob']);
    }

    public function test_update_returns_the_updated_model(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $updated = $this->repo->update($user, ['name' => 'Bob']);

        $this->assertSame('Bob', $updated->name);
    }

    // -------------------------------------------------------------------------
    // delete()
    // -------------------------------------------------------------------------

    public function test_delete_removes_the_record(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->repo->delete($user);

        $this->assertDatabaseMissing('fake_users', ['id' => $user->id]);
    }

    public function test_delete_calls_before_delete_then_after_deleted_in_order(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->repo->called = [];

        $this->repo->delete($user);

        $this->assertSame(['beforeDelete', 'afterDeleted'], $this->repo->called);
    }

    public function test_delete_accepts_a_raw_id(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->repo->delete($user->id);

        $this->assertDatabaseMissing('fake_users', ['id' => $user->id]);
    }

    public function test_delete_returns_true_on_success(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertTrue($this->repo->delete($user));
    }

    // -------------------------------------------------------------------------
    // withoutHooks() — skip all
    // -------------------------------------------------------------------------

    public function test_without_hooks_skips_all_hooks_on_create(): void
    {
        $this->repo->withoutHooks()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertEmpty($this->repo->called);
    }

    public function test_without_hooks_skips_all_hooks_on_update(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->repo->called = [];

        $this->repo->withoutHooks()->update($user, ['name' => 'Bob']);

        $this->assertEmpty($this->repo->called);
    }

    public function test_without_hooks_skips_all_hooks_on_delete(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->repo->called = [];

        $this->repo->withoutHooks()->delete($user);

        $this->assertEmpty($this->repo->called);
    }

    // -------------------------------------------------------------------------
    // withoutHooks() — skip specific
    // -------------------------------------------------------------------------

    public function test_without_hooks_skips_only_the_specified_hook(): void
    {
        $this->repo->withoutHooks('beforeCreate')->create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertNotContains('beforeCreate', $this->repo->called);
        $this->assertContains('afterCreated', $this->repo->called);
    }

    public function test_without_hooks_can_skip_multiple_specific_hooks(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->repo->called = [];

        $this->repo->withoutHooks('beforeUpdate', 'afterUpdated')->update($user, ['name' => 'Bob']);

        $this->assertEmpty($this->repo->called);
    }

    // -------------------------------------------------------------------------
    // withoutHooks() — resets after each operation
    // -------------------------------------------------------------------------

    public function test_without_hooks_resets_after_create_so_next_call_runs_hooks(): void
    {
        $this->repo->withoutHooks()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $this->repo->called = [];

        $this->repo->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->assertSame(['beforeCreate', 'afterCreated'], $this->repo->called);
    }

    public function test_without_hooks_resets_after_update_so_next_call_runs_hooks(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user2 = $this->repo->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $this->repo->called = [];

        $this->repo->withoutHooks()->update($user, ['name' => 'Alice2']);
        $this->repo->called = [];

        $this->repo->update($user2, ['name' => 'Bob2']);

        $this->assertSame(['beforeUpdate', 'afterUpdated'], $this->repo->called);
    }

    public function test_without_hooks_resets_after_delete_so_next_call_runs_hooks(): void
    {
        $user = $this->repo->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user2 = $this->repo->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $this->repo->called = [];

        $this->repo->withoutHooks()->delete($user);
        $this->repo->called = [];

        $this->repo->delete($user2);

        $this->assertSame(['beforeDelete', 'afterDeleted'], $this->repo->called);
    }

    // -------------------------------------------------------------------------
    // withoutHooks() — fluent interface
    // -------------------------------------------------------------------------

    public function test_without_hooks_returns_self_for_chaining(): void
    {
        $this->assertSame($this->repo, $this->repo->withoutHooks());
    }
}
