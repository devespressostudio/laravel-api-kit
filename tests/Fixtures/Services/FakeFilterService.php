<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Services;

use Devespresso\LaravelApiKit\Services\Filters\BaseFilterService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Concrete subclass of BaseFilterService for testing.
 *
 * - Overrides addSelectBasedOnTransformer() as a no-op so tests do not
 *   need a real transformer or bound class in the container.
 * - Tracks whether setConditions() was called and accepts an optional
 *   callback to configure the query inside setConditions().
 * - Exposes protected methods via public wrappers for direct unit testing.
 * - Exposes the internal $query property via getQuery() so tests can
 *   inspect orders, wheres, etc. without executing the query.
 */
class FakeFilterService extends BaseFilterService
{
    public $sortColumns = ['created_at', 'updated_at', 'id'];
    public $customSortColumns = [];
    public $rawSort = [];
    public $defaultSortingColumn = ['id,desc'];
    public $guardedMethods = [];
    public $adminMethods = [];
    public $autoApply = [];

    protected bool $conditionsCalled = false;
    protected $conditionsCallback = null;

    protected function addSelectBasedOnTransformer(): void
    {
        // No-op — transformer resolution is not needed in service unit tests.
    }

    protected function setConditions(): void
    {
        $this->conditionsCalled = true;

        if ($this->conditionsCallback) {
            ($this->conditionsCallback)($this->query);
        }
    }

    public function wasConditionsCalled(): bool
    {
        return $this->conditionsCalled;
    }

    public function setConditionsCallback(callable $callback): self
    {
        $this->conditionsCallback = $callback;

        return $this;
    }

    public function getQuery(): ?Builder
    {
        return $this->query;
    }

    public function callAddSelectAndEagerLoad($query, array $format, ?string $table = null): void
    {
        $this->addSelectAndEagerLoad($query, $format, $table);
    }

    public function callGetBaseGuardedMethods(): array
    {
        return $this->getBaseGuardedMethods();
    }

    public function callGetPaginationMethod(?string $type = null): string
    {
        return $this->getPaginationMethod($type);
    }
}
