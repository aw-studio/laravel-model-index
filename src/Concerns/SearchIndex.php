<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait SearchIndex
{
    protected $searchableFields = ['*'];

    public function searchable(array $fields)
    {
        $this->filterableFields = $fields;

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
                $query->orWhere($field, 'like', "%{$search}%");
            }
        });

        return $this;
    }

    protected function getSearchableFields(): array
    {
        return $this->searchableFields === ['*']
            ? $this->query()->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($this->query()->getModel()->getTable())
            : $this->searchableFields;
    }
}
