<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Repositories;

use Devespresso\LaravelApiKit\Repositories\BaseRepository;
use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Concrete subclass of BaseRepository for testing.
 *
 * - Overrides resolveModel() to return FakeUser so no config/container is needed.
 * - Records every hook invocation in $called so tests can assert execution order.
 */
class FakeRepository extends BaseRepository
{
    public array $called = [];

    protected function resolveModel(): Model
    {
        return new FakeUser();
    }

    protected function beforeCreate(array &$attributes = []): void
    {
        $this->called[] = 'beforeCreate';
    }

    protected function afterCreated(Model $model, array $attributes): void
    {
        $this->called[] = 'afterCreated';
    }

    protected function beforeUpdate(?Model $model = null, array &$attributes = []): void
    {
        $this->called[] = 'beforeUpdate';
    }

    protected function afterUpdated(Model $model, array $attributes): void
    {
        $this->called[] = 'afterUpdated';
    }

    protected function beforeDelete(Model $model): void
    {
        $this->called[] = 'beforeDelete';
    }

    protected function afterDeleted(Model $model): void
    {
        $this->called[] = 'afterDeleted';
    }
}
