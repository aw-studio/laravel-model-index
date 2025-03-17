<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait SearchIndex
{
    protected $searchableFields = ['*'];

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
                if (strpos($field, '.') !== false) {
                    [$relation, $field] = explode('.', $field);

                    $query = $this->searchRelated($query, $relation, $search);

                    continue;
                }
                $query->orWhere($field, 'like', "%{$search}%");
            }
        });

        return $this;
    }

    protected function searchRelated($query, $field, $search)
    {
        return $query->orWhereHas($field, function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%");
        });
    }

    protected function getSearchableFields(): array
    {
        return $this->searchableFields === ['*']
            ? $this->query()->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($this->query()->getModel()->getTable())
            : $this->searchableFields;
    }
}
