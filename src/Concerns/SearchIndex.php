<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait SearchIndex
{
    protected $searchableFields = ['*'];

    /**
     * @var array Custom search callbacks.
     */
    protected $customCallbacks = [];

    /**
     * Register a custom search callback.
     *
     * @param  string  $key
     * @param  callable  $callback
     * @return $this
     */
    public function search($key, $callback)
    {
        $this->customCallbacks[$key] = $callback;

        return $this;
    }

    public function searchable(array $fields)
    {
        $this->searchableFields = $fields;

        return $this;
    }

    public function searchFromRequest(Request $request)
    {
        $search = $request->get('search');

        if (! $search) {
            return $this;
        }

        $this->query()->where(function ($query) use ($search) {
            foreach ($this->getSearchableFields() as $field) {
                if (isset($this->customCallbacks[$field])) {
                    $callback = $this->customCallbacks[$field];
                    $callback($query, $search);

                    continue;
                }
                if (strpos($field, '.') !== false) {
                    [$relation, $field] = explode('.', $field);

                    $query = $this->searchRelated($query, $relation, $field, $search);

                    continue;
                }
                $query->orWhere($field, 'like', "%{$search}%");
            }
        });

        return $this;
    }

    protected function searchRelated($query, $relation, $field, $search)
    {
        return $query->orWhereHas($relation, function ($query) use ($search, $field) {
            $query->where($field, 'like', "%{$search}%");
        });
    }

    protected function getSearchableFields(): array
    {
        return $this->searchableFields === ['*']
            ? $this->query()->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($this->query()->getModel()->getTable())
            : [
                ...$this->searchableFields,
                ...array_keys($this->customCallbacks),
            ];
    }
}
