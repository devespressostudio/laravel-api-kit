<?php

namespace Devespresso\LaravelApiKit\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct()
    {
        $this->model = $this->resolveModel();
    }

    /**
     * Returns a paginated/filtered list of records.
     */
    public function index(
        array $data,
        ?Authenticatable $user = null,
        $query = null,
        array $extras = []
    ): mixed {
        return $this->model->filter($data, $user, $query, $extras);
    }

    /**
     * Finds a single record by ID.
     */
    public function get(int|string $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * Called before a model is created. Modify $attributes by reference to alter what gets persisted.
     */
    protected function beforeCreate(array &$attributes = []): void
    {
    }

    /**
     * Creates a new record.
     */
    public function create(array $attributes): Model
    {
        $this->beforeCreate($attributes);

        $model = $this->model->create($attributes);

        $this->afterCreated($model, $attributes);

        return $model;
    }

    /**
     * Called after a model is created.
     */
    protected function afterCreated(Model $model, array $attributes): void
    {
    }

    /**
     * Called before a model is updated. Modify $attributes by reference to alter what gets persisted.
     */
    protected function beforeUpdate(?Model $model = null, array &$attributes = []): void
    {
    }

    /**
     * Updates a record. Accepts a Model instance or a raw ID.
     */
    public function update(Model|int|string $model, array $attributes): Model
    {
        if (! $model instanceof Model) {
            $model = $this->get($model);
        }

        $this->beforeUpdate($model, $attributes);

        return tap($model, function ($model) use ($attributes) {
            $model->update($attributes);

            $this->afterUpdated($model, $attributes);
        });
    }

    /**
     * Called after a model is updated.
     */
    protected function afterUpdated(Model $model, array $attributes): void
    {
    }

    /**
     * Deletes a record. Accepts a Model instance or a raw ID.
     */
    public function delete(Model|int|string $model): bool
    {
        if (! $model instanceof Model) {
            $model = $this->get($model);
        }

        return $model->delete();
    }

    /**
     * Resolves the Eloquent model for this repository.
     *
     * If $model is pre-set on the subclass as a class string, that class is instantiated.
     * Otherwise, the model is inferred from the repository class name by stripping "Repository"
     * and prepending the configured models namespace (devespressoApi.paths.models).
     *
     * Example: UserRepository → {models_path}\User
     *
     * @throws \RuntimeException if the resolved class does not exist.
     */
    protected function resolveModel(): Model
    {
        $class = isset($this->model) && is_string($this->model)
            ? $this->model
            : (string) Str::of(class_basename($this))
                ->prepend(config('devespressoApi.paths.models'))
                ->replace('Repository', '');

        if (! class_exists($class)) {
            throw new \RuntimeException("Model class [{$class}] could not be found for [".static::class.'].');
        }

        return new $class();
    }
}
