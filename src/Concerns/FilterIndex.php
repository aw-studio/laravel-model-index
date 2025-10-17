<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * @mixin \AwStudio\ModelIndex\IndexQueryBuilder
 *
 * @property \Illuminate\Database\Eloquent\Builder $query
 */
trait FilterIndex
{
    use ParsesFiltersFromRequest;

    /**
     * @var array List of fields that can be filtered.
     */
    protected $filterableFields = [];

    /**
     * @var array Custom filter callbacks.
     */
    protected $customFilterCallbacks = [];

    /**
     * Set the fields that can be filtered.
     *
     * @return $this
     */
    public function filterable(array $fields)
    {
        $this->filterableFields = $fields;

        return $this;
    }

    /**
     * Apply filters from the request to the query.
     *
     * @return $this
     */
    public function filterFromRequest(Request $request)
    {
        $filters = $this->getFiltersFromRequest($request);

        $this->applyFilters($this->query, $filters);

        return $this;
    }

    /**
     * Register a custom filter callback.
     *
     * @param  string  $key
     * @param  callable  $callback
     * @return $this
     */
    public function filter($key, $callback)
    {
        $this->customFilterCallbacks[$key] = $callback;

        return $this;
    }

    /**
     * Get the list of filterable fields.
     *
     * @return array
     */
    public function getFilterableFields()
    {
        // If the filterable fields are set, return them.
        if ($this->filterableFields !== []) {
            return $this->filterableFields;
        }

        // If the model has a filterable method, return the fields from there.
        if (method_exists($this->query->getModel(), 'filterable')) {
            return $this->query->getModel()->filterable();
        }

        // If the model has a $filterable property, return the fields from there.
        if (property_exists($this->query->getModel(), 'filterable')) {
            return $this->query->getModel()->filterable;
        }

        // If none of the above, return an empty array by default.
        return [];
    }

    /**
     * Apply filters to the query.
     *
     * @param  array  $filters
     * @param  string  $logicalOperator
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function applyFilters(Builder $query, $filters, $logicalOperator = '$and')
    {
        foreach ($filters as $key => $filter) {

            if ($this->isNestedFilter($filter)) {
                $this->handleNestedFilter($query, $filter, $key, $logicalOperator);

                continue;
            }

            // Handle nested logical operators like e.g. ['name' => 'John', '$or' => ['age' => 30, 'age' => 40]]
            if (isset($filter['$or']) || isset($filter['$and'])) {
                $query->where(function ($query) use ($filter) {
                    $this->applyFilters($query, $filter);
                });

                continue;
            }

            // Handle normal filters
            [$field, $operator, $value] = $filter;

            if ($field == '' || $operator == '') {
                continue;
            }

            // check if custom filter callback is set
            if (isset($this->customFilterCallbacks[$field])) {
                $this->customFilterCallbacks[$field]($this->query, $value);

                continue;
            }

            if ($this->getFilterableFields() != ['*'] && ! in_array($field, $this->getFilterableFields())) {
                throw new \InvalidArgumentException("Filtering by {$field} is not allowed.");
            }

            match ($operator) {
                '=', '!=', '>', '>=', '<', '<=', 'like', 'not like' => $this->applyBasicCondition($query, $field, $operator, $value, $logicalOperator),
                'in' => $this->applyWhereInCondition($query, $field, $value, $logicalOperator),
                'not in' => $this->applyWhereNotInCondition($query, $field, $value, $logicalOperator),
                'between' => $this->applyWhereBetweenCondition($query, $field, $value, $logicalOperator),
                'null' => $this->applyWhereNullCondition($query, $field, $logicalOperator),
                'not null' => $this->applyWhereNotNullCondition($query, $field, $logicalOperator),
                default => throw new \InvalidArgumentException("Unsupported operator '{$operator}' for field '{$field}'"),
            };
        }
    }

    /**
     * Check if the filter is nested.
     *
     * @param  mixed  $filter
     * @return bool
     */
    protected function isNestedFilter($filter)
    {
        return is_array($filter) && isset($filter[0]) && is_array($filter[0]);
    }

    /**
     * Handle nested filters.
     *
     * @param  array  $filter
     * @param  string  $key
     * @param  string  $logicalOperator
     * @return void
     */
    protected function handleNestedFilter(Builder $query, $filter, $key, $logicalOperator = '$and')
    {
        $filterOperator = $key === '$and' || $key === '$or' ? $key : $logicalOperator;

        if ($key === '$or') {
            $query->where(function ($query) use ($filter, $filterOperator) {
                $this->applyFilters($query, $filter, $filterOperator);
            });

            return;
        }

        $this->applyFilters($query, $filter, $filterOperator);
    }

    /**
     * Apply a basic condition to the query.
     *
     * @param  string  $field
     * @param  string  $operator
     * @param  mixed  $value
     * @param  string  $logicalOperator
     * @return void
     */
    protected function applyBasicCondition(Builder $query, $field, $operator, $value, $logicalOperator)
    {
        $query->where($field, $operator, $value, $logicalOperator === '$or' ? 'or' : 'and');
    }

    /**
     * Apply a "where in" condition to the query.
     */
    protected function applyWhereInCondition(Builder $query, string $field, array $value, string $logicalOperator): void
    {
        $query->whereIn($field, $value, $logicalOperator === '$or' ? 'or' : 'and');
    }

    /**
     * Apply a "where not in" condition to the query.
     */
    protected function applyWhereNotInCondition(Builder $query, string $field, array $value, string $logicalOperator): void
    {
        $query->whereNotIn($field, $value, $logicalOperator === '$or' ? 'or' : 'and');
    }

    /**
     * Apply a "where between" condition to the query.
     */
    protected function applyWhereBetweenCondition(Builder $query, string $field, array $value, string $logicalOperator): void
    {
        $query->whereBetween($field, $value, $logicalOperator === '$or' ? 'or' : 'and');
    }

    /**
     * Apply a "where null" condition to the query.
     */
    protected function applyWhereNullCondition(Builder $query, string $field, string $logicalOperator): void
    {
        $query->whereNull($field, $logicalOperator === '$or' ? 'or' : 'and');
    }

    /**
     * Apply a "where not null" condition to the query.
     */
    protected function applyWhereNotNullCondition(Builder $query, string $field, string $logicalOperator): void
    {
        $query->whereNotNull($field, $logicalOperator === '$or' ? 'or' : 'and');
    }
}
