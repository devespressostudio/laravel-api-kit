<?php

namespace Devespresso\LaravelApiKit\Services\Filters;

use Devespresso\LaravelApiKit\Transformers\BaseTransformer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

class BaseFilterService
{
    /**
     * Class string or instance of the transformer to use.
     * Resolved automatically from the model name if not set.
     *
     * @var string|object|null
     */
    protected string|object|null $transformer = null;

    /**
     * Select
     *
     * @var array
     */
    protected $select = [];

    /**
     * User
     *
     * @var Authenticatable
     */
    protected $user = null;

    /**
     * Model
     *
     * @var Model
     */
    protected $model = null;

    /**
     * Data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Extra properties
     *
     * @var array
     */
    protected $extras = [];

    /**
     * Builder
     *
     * @var Builder
     */
    protected $query = null;

    /**
     * Allow sorting
     *
     * @var array
     */
    protected $sortColumns = [
        'created_at',
        'updated_at',
        'id',
    ];

    /**
     * Custom Sort Columns
     *
     * @var array
     */
    protected $customSortColumns = [];

    /**
     * Sets the default sorting
     *
     * @var array
     */
    protected $defaultSortingColumn = ['id,desc'];

    /**
     * Methods that cannot be triggered by the data
     *
     * @var array
     */
    protected $guardedMethods = [];

    /**
     * Methods that admin users can trigger
     *
     * @var array
     */
    protected $adminMethods = [];

    /**
     * Methods that are going to be applied
     *
     * @var array
     */
    protected $autoApply = [];

    /**
     * Run conditions
     *
     * @var bool
     */
    protected $runConditions = true;

    /**
     * Sets the authenticated user for this filter session.
     *
     * The user is used to determine access to admin-only filter methods.
     * Pass null to represent an unauthenticated (guest) context.
     */
    public function setUser(?Authenticatable $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Sets the input data used to drive filtering.
     *
     * Keys in the array are mapped to filter methods on the subclass via camelCase
     * conversion. For example, passing ['created_after' => '2024-01-01'] will call
     * $this->createdAfter('2024-01-01') if that method exists and is not guarded.
     */
    public function setData(array $data = []): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Sets arbitrary extra context that filter methods can read via getExtraProperty().
     *
     * Use this to pass data that should influence filtering but does not come from
     * user input — for example, a tenant ID or a parent resource ID resolved by a
     * controller before invoking the filter service.
     *
     * @param  array  $extras
     */
    public function setExtras(array $extras = []): self
    {
        $this->extras = $extras;

        return $this;
    }

    /**
     * Sets the Eloquent query builder that filters will be applied to.
     *
     * Only sets the query if one has not already been assigned, so calling this
     * multiple times is safe. Internally, filter() initialises the query from the
     * model, but you can supply a pre-scoped builder here when needed.
     *
     * @param  Builder  $query
     */
    public function setQuery($query): self
    {
        if (!$this->query) {
            $this->query = $query;
        }

        return $this;
    }

    /**
     * Sets the Eloquent model that the filter service operates on.
     *
     * The model is used to initialise the query builder in filter() and to derive
     * the table name and per-page defaults. Must be set before calling filter().
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Override in subclasses to apply baseline query constraints.
     *
     * Called automatically by filter() before user-driven filters are applied
     * (unless conditions have been disabled via disableConditions()). Use this
     * method to enforce business rules that should always be present regardless
     * of the incoming request data — for example, scoping results to the current
     * user's records or filtering out soft-deleted items.
     */
    protected function setConditions(): void
    {
    }

    /**
     * Iterates over the given filters array and calls the matching method on this service.
     *
     * Each key in $filters is converted to camelCase and, if a matching public method
     * exists on the service and is not listed in $guarded, that method is called with
     * the corresponding value. This powers the automatic dispatch of request data to
     * individual filter methods without manual if/switch logic.
     *
     * @param  array  $filters  Key/value pairs of filter name => value.
     * @param  array  $guarded  Method names that must not be called via this dispatch.
     */
    protected function setFilters(
        array $filters,
        array $guarded = []
    ): void {
        foreach ($filters as $method => $value) {
            $method = Str::camel($method);
            if (!in_array($method, $guarded) && method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * Overrides the columns selected by the query.
     *
     * When a non-empty array is provided it replaces the current $select list.
     * If an empty array is passed the existing $select value is preserved unchanged.
     * This is applied during filter() after the transformer-derived columns are set.
     */
    public function setSelect(array $value = []): void
    {
        $this->select = count($value) ? $value : $this->select;
    }

    /**
     * Prevents setConditions() from running during the next filter() call.
     *
     * Useful in internal or admin contexts where the baseline query constraints
     * defined in setConditions() should be skipped — for example, when fetching
     * results on behalf of another user or for a background job.
     */
    public function disableConditions(): void
    {
        $this->runConditions = false;
    }

    /**
     * Executes the full filter pipeline and returns paginated results.
     *
     * Steps performed in order:
     *  1. Initialises the query builder from the model.
     *  2. Adds SELECT columns and eager-loads derived from the transformer.
     *  3. Applies any explicit $select overrides.
     *  4. Runs setConditions() unless conditions have been disabled.
     *  5. Dispatches each key in $data to its matching filter method, skipping
     *     guarded, admin-only (for non-admin users), and base-class methods.
     *  6. Applies the default sort if no 'sort' key was present in $data.
     *  7. Dispatches $autoApply filters (always applied, no guarding).
     *  8. Paginates the results using the method determined by 'pagination_type'.
     *
     * @return Builder
     */
    public function filter()
    {
        $this->setQuery($this->model->query());

        $this->addSelectBasedOnTransformer();

        if (count($this->select)) {
            $this->query->select($this->select);
        }

        if ($this->runConditions) {
            $this->setConditions();
        }

        // Apply filters
        $this->setFilters($this->data, [
            ...$this->getBaseGuardedMethods(),
            ...$this->guardedMethods,
            ...$this->userIsAdmin() ? [] : $this->adminMethods,
        ]);

        if (!isset($this->data['sort'])) {
            $this->sort($this->defaultSortingColumn);
        }

        // Apply automatic filters
        $this->setFilters($this->autoApply);

        $paginateMethod = $this->getPaginationMethod(
            $this->getDataValue('pagination_type')
        );

        if ($paginateMethod === 'get') {
            return $this->query->get();
        }

        return $this->query->$paginateMethod($this->getPerPage());
    }

    /**
     * Delegates full-text search to the underlying query builder.
     *
     * Requires the model's query builder to implement a search() scope (e.g. via
     * a Laravel Scout integration or a custom local scope). The search term comes
     * from the 'search' key in the request data and is dispatched automatically
     * by setFilters().
     */
    public function search(?string $search): void
    {
        $this->query->search($search);
    }

    /**
     * Applies one or more sort directives to the query.
     *
     * Each value in $sortValues should be a string in "column,direction" format
     * (e.g. "created_at,desc"). A bare string is also accepted and treated as a
     * single-item list. Each directive is validated and applied via applySort().
     *
     * @param  array|string  $sortValues  One or more "column,direction" sort strings.
     * @param  bool  $hasBeenRenamed  Internal flag — true when the column was already
     *                                 resolved from a $customSortColumns alias, preventing
     *                                 an infinite redirect loop.
     */
    public function sort(array|string $sortValues, bool $hasBeenRenamed = false): self
    {
        $sortValues = is_iterable($sortValues) ? $sortValues : [$sortValues];
        foreach ($sortValues as $sort) {
            $this->applySort($sort, $hasBeenRenamed);
        }

        return $this;
    }

    /**
     * Validates and applies a single "column,direction" sort string to the query.
     *
     * Behaviour:
     *  - Parses the column and direction (defaults to 'desc' if omitted).
     *  - If the column matches a key in $customSortColumns, the sort is redirected
     *    to the mapped column name with $hasBeenRenamed=true to avoid loops.
     *  - If the column is not in the merged $sortColumns + $customSortColumns allow-list
     *    and has not already been renamed, falls back to $defaultSortingColumn.
     *  - Otherwise applies an orderBy() to the query.
     */
    public function applySort(string $sort, bool $hasBeenRenamed = false): void
    {
        // Extract the values
        [$column, $order] = array_pad(explode(',', $sort), 2, 'desc');
        // Sets the order
        $order = $order === 'asc' ? 'asc' : 'desc';
        // Allowed columns
        $allowedColumns = array_merge($this->sortColumns, $this->customSortColumns);
        // Check if it is a renamed column
        if (array_key_exists($column, $allowedColumns)) {
            $this->sort($allowedColumns[$column], true);

            return;
        }
        // Check if the order is allowed
        if (!in_array($column, $allowedColumns) && !$hasBeenRenamed) {
            $this->sort($this->defaultSortingColumn, true);

            return;
        }
        $this->query->orderBy($column, $order);
    }

    /**
     * Eager-loads the given relationships on the current query.
     *
     * A convenience wrapper around $query->with() for use inside filter methods
     * and setConditions(). Accepts the same argument formats as Eloquent's with().
     */
    protected function with(array $data): void
    {
        $this->query->with($data);
    }

    /**
     * Adds relationship counts to the current query.
     *
     * A convenience wrapper around $query->withCount() for use inside filter
     * methods and setConditions(). Each entry in $data follows the same format
     * as Eloquent's withCount().
     */
    protected function withCount(array $data): void
    {
        $this->query->withCount($data);
    }

    /**
     * Checks whether a specific key in $data equals a given value.
     *
     * Returns false if the key does not exist at all, so this is safe to call
     * without first checking for key presence. Useful inside filter methods to
     * branch on the value of a sibling data key.
     *
     * @param  mixed  $value
     */
    public function dataHasValue(string $key, $value): bool
    {
        if (!array_key_exists($key, $this->data)) {
            return false;
        }

        return $this->data[$key] === $value;
    }

    /**
     * Returns the value of a key from $data, or a default if it is absent.
     *
     * Uses dataHasKeys() internally so that null values stored under a key are
     * returned correctly rather than falling through to $default.
     *
     * @return mixed
     */
    public function getDataValue(string $key, $default = null)
    {
        if (!$this->dataHasKeys([$key])) {
            return $default;
        }

        return $this->data[$key];
    }

    /**
     * Returns true only if every key in $keys is present in $data.
     *
     * Useful for guarding filter logic that requires multiple data keys to be
     * present before it can safely execute.
     */
    public function dataHasKeys(array $keys = []): bool
    {
        $dataKeys = array_keys($this->data);

        return count(array_intersect($dataKeys, $keys)) === count($keys);
    }

    /**
     * Returns a value from the $extras array by property name.
     *
     * Returns null (via optional()) if the property does not exist, so callers
     * do not need to guard against missing keys. Use setExtras() to populate
     * this bag before invoking filter().
     */
    public function getExtraProperty(string $property): mixed
    {
        return optional($this->extras)[$property];
    }

    /**
     * Resolves the transformer and uses its formatting definition to set SELECT columns
     * and eager-load relationships on the query.
     *
     * Called once at the start of filter(). Delegates the actual column and relation
     * logic to addSelectAndEagerLoad() so the same recursive behaviour applies to
     * nested relationships.
     */
    protected function addSelectBasedOnTransformer(): void
    {
        $transformer = $this->guessTransformer();
        $format = $transformer->getFormatting();
        $this->addSelectAndEagerLoad($this->query, $format);
    }

    /**
     * Recursively applies SELECT columns and eager-loads relationships based on a
     * transformer formatting array.
     *
     * When devespressoApi.auto_select is enabled:
     *  - Scalar entries in $format are treated as column names and added to the SELECT
     *    list, prefixed with the table name to avoid ambiguity in joins.
     *  - Hidden-attribute prefixes are stripped before adding columns.
     *  - Columns that start with the custom-attributes prefix are excluded (they are
     *    computed, not stored in the database).
     *
     * When devespressoApi.auto_eager_load is enabled:
     *  - Array entries in $format are treated as relationship definitions and loaded
     *    via with(), recursively calling this method for nested column selection.
     *
     * @param  Builder  $query   The query builder to modify (may be a relation query).
     * @param  array  $format    Transformer formatting array (scalars = columns, arrays = relations).
     * @param  string|null  $table  Table name to prefix columns with. Inferred from the model when null.
     */
    protected function addSelectAndEagerLoad($query, array $format, ?string $table = null): void
    {
        if (config('devespressoApi.auto_select')) {
            $columns = array_filter($format, fn ($item) => !is_array($item));
            if (!count($columns)) {
                $columns = ['*'];
            }
            $hiddenAttributes = array_map(fn ($item) => Str::replaceFirst(
                config('devespressoApi.transformers.prefixes.hidden_attributes'),
                '',
                $item
            ), $columns);
            /**
             * Remove Custom attributes
             */
            $columnWithoutCustom = array_filter(
                $hiddenAttributes,
                fn ($item) =>
                !Str::startsWith(
                    $item,
                    config('devespressoApi.transformers.prefixes.custom_attributes')
                )
            );

            if (!$table) {
                $table = $this->model->getTable();
            }

            $columnsWithTableName = array_map(fn ($col) => $table . '.' . $col, $columnWithoutCustom);

            $query->addSelect($columnsWithTableName);
        }
        if (config('devespressoApi.auto_eager_load')) {
            foreach ($format as $relation => $select) {
                if (!is_array($select)) {
                    continue;
                }
                $relation = Str::replaceFirst(
                    config('devespressoApi.transformers.prefixes.hidden_attributes'),
                    '',
                    $relation
                );
                $query->with($relation, function ($query) use ($select) {
                    $this->addSelectAndEagerLoad($query, $select, $query->getQuery()?->from);
                });
            }
        }
    }

    /**
     * Resolves the transformer class to use for this filter service.
     *
     * If $transformer is explicitly set on the subclass, that class is resolved
     * from the container. Otherwise the transformer is inferred from the model's
     * class basename by appending "Transformer" and prepending the configured
     * transformers namespace (devespressoApi.paths.transformers).
     *
     * For example, a model named "Product" would resolve to "{namespace}ProductTransformer".
     */
    protected function guessTransformer(): BaseTransformer
    {

        if ($this->transformer) {
            return resolve($this->transformer);
        }

        $transformer = (string) Str::of(class_basename($this->model))
            ->prepend(config('devespressoApi.paths.transformers'))
            ->append('Transformer');

        return resolve($transformer);
    }

    /**
     * Returns true if the current user has admin privileges.
     *
     * Checks for an isAdmin() method on the user object and calls it. If the method
     * does not exist (e.g. a guest or a non-admin user model), returns false safely.
     * This controls whether methods listed in $adminMethods are accessible.
     */
    private function userIsAdmin(): bool
    {
        return $this->user !== null && method_exists($this->user, 'isAdmin') && (bool) call_user_func([$this->user, 'isAdmin']);
    }

    /**
     * Returns the names of all public methods defined directly on BaseFilterService.
     *
     * These are used as the default guard list in filter() to ensure that internal
     * framework methods (like setQuery, setData, filter itself, etc.) cannot be
     * triggered by keys in the incoming request data. 'sort' and 'search' are
     * intentionally excluded so they remain dispatchable as filter methods.
     */
    protected function getBaseGuardedMethods(): array
    {
        $class = new ReflectionClass(BaseFilterService::class);

        return array_filter(array_map(function ($method) {
            return $method->getName();
        }, $class->getMethods()), function ($item) {
            return !in_array($item, ['sort', 'search']);
        });
    }

    /**
     * Determines which Eloquent pagination method to use based on the requested type.
     *
     * Pagination type values and their behaviour:
     *  - null     → uses paginate() or simplePaginate() based on the devespressoApi config default.
     *  - 'none'   → uses get() to return all results without pagination.
     *  - 'simple' → uses simplePaginate() (no total count query).
     *  - anything else (e.g. 'full') → uses paginate() (includes total count).
     */
    protected function getPaginationMethod(?string $paginationType = null): string
    {
        if (is_null($paginationType)) {
            return config('devespressoApi.pagination.with_pages') ? 'paginate' : 'simplePaginate';
        }

        if ($paginationType === 'none') {
            return 'get';
        }

        return $paginationType !== 'simple' ? 'paginate' : 'simplePaginate';
    }

    /**
     * Returns the current page number from the request data.
     *
     * Reads the 'page' key from $data and defaults to 1 if not provided.
     */
    protected function getPage(): int
    {
        return $this->getDataValue('page', 1);
    }

    /**
     * Returns the number of results per page from the request data.
     *
     * Reads the 'per_page' key from $data and falls back to the model's own
     * default per-page value (set via $perPage on the model) if not specified.
     */
    protected function getPerPage(): int
    {
        return $this->getDataValue('per_page', $this->model->getPerPage());
    }
}
