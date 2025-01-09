<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait SortIndex
{
    protected $sortableFields = ['*'];

    public function sortable(array $fields)
    {
        $this->sortableFields = $fields;

        return $this;
    }

    /**
     * Sort the query based on the request parameters.
     *
     * @param \Illuminate\Http\Request $request
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
