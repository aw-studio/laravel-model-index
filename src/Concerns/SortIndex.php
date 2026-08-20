<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait SortIndex
{
    /**
     * The fields that may be sorted by on this builder instance.
     *
     * `null` means "not explicitly set", in which case the application-wide
     * default from `defaultSortable()` applies.
     *
     * @var array|null
     */
    protected $sortableFields = null;

    /**
     * The application-wide default sortable fields.
     *
     * Defaults to `['*']` — allow every column — which is kept for backwards
     * compatibility. Public index endpoints should opt into a strict default
     * by calling `IndexQueryBuilder::defaultSortable([])` in a service
     * provider, so that any endpoint which forgets to call `sortable([...])`
     * rejects sorting instead of silently ordering by an arbitrary column.
     *
     * @var array
     */
    protected static $defaultSortableFields = ['*'];

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
     * Set the application-wide default sortable fields.
     *
     * Call this once during boot. Pass `[]` to deny sorting unless an index
     * explicitly opts in via `sortable([...])`, or `['*']` to restore the
     * permissive default.
     *
     * @return void
     */
    public static function defaultSortable(array $fields)
    {
        static::$defaultSortableFields = $fields;
    }

    /**
     * Get the list of sortable fields.
     *
     * @return array
     */
    public function getSortableFields()
    {
        return $this->sortableFields ?? static::$defaultSortableFields;
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
