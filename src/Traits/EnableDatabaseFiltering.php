<?php

namespace Devespresso\LaravelApiKit\Traits;

use Devespresso\LaravelApiKit\Services\Filters\BaseFilterService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

trait EnableDatabaseFiltering
{
    /**
     * Apply filters
     *
     * @param  Builder  $query
     * @return mixed
     */
    public function filter(
        array $data,
        ?Authenticatable $user,
        $query = null,
        array $extras = []
    ) {
        $filterService = BaseFilterService::class;

        if (property_exists($this, 'defaultFilterService')) {
            $filterService = $this->defaultFilterService;
        }

        return (new $filterService())
            ->setModel($this)
            ->setQuery($query)
            ->setUser($user)
            ->setData($data)
            ->setExtras($extras)
            ->filter();
    }

    /**
     * Search
     *
     * @return Builder
     */
    public function scopeSearch(Builder $builder, ?string $search)
    {
        if (!$search) {
            return $builder;
        }
        $columns = $this->searchableColumns ?? [];
        $terms = explode(' ', $search);
        foreach ($terms as $term) {
            $builder->where(function ($query) use ($columns, $term) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'LIKE', '%' . $term . '%');
                }
            });
        }

        return $builder;
    }
}
