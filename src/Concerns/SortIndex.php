<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait SortIndex
{
    protected $sortableFields = ['*'];

    /**
     * @var array Custom sorting callbacks.
     */
    protected $customCallbacks = [];

    public function sortable(array $fields)
    {
        $this->sortableFields = $fields;

        return $this;
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
        $this->customCallbacks[$key] = $callback;

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

            if ($this->sortableFields != ['*'] && ! in_array($sortField, $this->sortableFields)) {
                throw new \InvalidArgumentException("Sorting by {$sortField} is not allowed.");
            }

            if (isset($this->customCallbacks[$sortField])) {
                $this->customCallbacks[$sortField]($this->query(), $sortDirection);

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
