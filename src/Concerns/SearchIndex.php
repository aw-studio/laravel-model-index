<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

trait SearchIndex
{
    /**
     * The fields searched by `?search=` on this builder instance.
     *
     * `null` means "not explicitly set", in which case the application-wide
     * default from `defaultSearchable()` applies.
     *
     * @var array|null
     */
    protected $searchableFields = null;

    /**
     * The application-wide default searchable fields.
     *
     * Defaults to `['*']` — expand to the table's columns — which is kept for
     * backwards compatibility. Apps that want search to be opt-in per index can
     * call `IndexQueryBuilder::defaultSearchable([])` in a service provider.
     *
     * @var array
     */
    protected static $defaultSearchableFields = ['*'];

    /**
     * @var array Custom search callbacks.
     */
    protected $customSearchCallbacks = [];

    /**
     * Register a custom search callback.
     *
     * @param  string  $key
     * @param  callable  $callback
     * @return $this
     */
    public function search($key, $callback)
    {
        $this->customSearchCallbacks[$key] = $callback;

        return $this;
    }

    public function searchable(array $fields)
    {
        $this->searchableFields = $fields;

        return $this;
    }

    /**
     * Set the application-wide default searchable fields.
     *
     * Call this once during boot. Pass `[]` to require every index to opt in
     * via `searchable([...])`, or `['*']` to restore the permissive default.
     *
     * @return void
     */
    public static function defaultSearchable(array $fields)
    {
        static::$defaultSearchableFields = $fields;
    }

    /**
     * Search the query based on the request parameters.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function searchFromRequest(Request $request)
    {
        $search = $request->get('search');

        if (! $search) {
            return $this;
        }

        $fields = $this->getSearchableFields();

        // An empty searchable set cannot honour the term. Silently dropping it
        // would return the whole unfiltered list, which reads as "everything
        // matched" rather than "this index has no searchable columns".
        if ($fields === []) {
            throw new \InvalidArgumentException('Searching is not allowed on this index.');
        }

        $this->query()->where(function ($query) use ($search, $fields) {
            foreach ($fields as $field) {
                if (isset($this->customSearchCallbacks[$field])) {
                    $callback = $this->customSearchCallbacks[$field];
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

    /**
     * Get the configured searchable fields, before wildcard expansion.
     */
    protected function configuredSearchableFields(): array
    {
        return $this->searchableFields ?? static::$defaultSearchableFields;
    }

    protected function getSearchableFields(): array
    {
        $configured = $this->configuredSearchableFields();

        return $configured === ['*']
            ? $this->searchableColumns()
            : [
                ...$configured,
                ...array_keys($this->customSearchCallbacks),
            ];
    }

    /**
     * Expand `['*']` to the table's columns, minus anything the model hides.
     *
     * `$hidden` only affects serialization, never `WHERE` clauses, so without
     * this the wildcard matched `?search=` against password hashes and remember
     * tokens — and because the row count differs on a hit, that is a usable
     * prefix oracle for extracting a secret.
     */
    protected function searchableColumns(): array
    {
        $model = $this->query()->getModel();

        $columns = $model->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($model->getTable());

        return array_values(array_diff($columns, $model->getHidden()));
    }
}
