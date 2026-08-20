<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait SortIndex
{
    /**
     * The fields that may be sorted by on this builder instance.
     *
     * `null` means "not explicitly set", in which case the `model-index.sortable`
     * config value applies.
     *
     * @var array|null
     */
    protected $sortableFields = null;

    /**
     * @var array Custom sorting callbacks.
     */
    protected $customSortCallbacks = [];

    public function sortable(array $fields)
    {
        $this->sortableFields = $fields;

        return $this;
    }

    /**
     * Get the list of sortable fields.
     *
     * Falls back to the `model-index.sortable` config value when this index has
     * not declared its own list.
     *
     * @return array
     */
    public function getSortableFields()
    {
        return $this->sortableFields ?? config('model-index.sortable', ['*']);
    }

    /**
     * Register a custom sorting callback.
     *
     * @param  string  $key
     * @param  callable  $callback
     * @return $this
     */
    public function sort($key, $callback)
    {
        $this->customSortCallbacks[$key] = $callback;

        return $this;
    }

    /**
     * Sort the query based on the request parameters.
     *
     * @return $this
     */
    public function sortFromRequest(Request $request)
    {
        if (! $request->has('sort')) {
            return $this;
        }

        foreach (explode(',', $request->get('sort')) as $sortField) {

            $sortDirection = $this->determineSortDirection($sortField);

            $sortField = $this->clean($sortField);

            $sortableFields = $this->getSortableFields();

            if ($sortableFields != ['*'] && ! in_array($sortField, $sortableFields)) {
                throw new \InvalidArgumentException("Sorting by {$sortField} is not allowed.");
            }

            if (isset($this->customSortCallbacks[$sortField])) {
                $this->customSortCallbacks[$sortField]($this->query(), $sortDirection);

                continue;
            }
            $this->query()->orderBy($sortField, $sortDirection);

        }

        return $this;
    }

    protected function determineSortDirection($sortField)
    {
        $sortDirection = 'asc';

        if (preg_match('/:(asc|desc)$/', $sortField)) {
            [$_, $sortDirection] = explode(':', $sortField);
        }

        if (str_starts_with($sortField, '-')) {
            $sortDirection = 'desc';
        }

        return $sortDirection;
    }

    protected function clean($sortField)
    {
        $sortField = ltrim($sortField, '-');
        $sortField = preg_replace('/:(asc|desc)$/', '', $sortField);

        return $sortField;
    }
}
