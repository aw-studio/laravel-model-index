<?php

namespace AwStudio\ModelIndex;

use Illuminate\Http\Request;
use AwStudio\ModelIndex\Concerns\SortIndex;
use AwStudio\ModelIndex\Concerns\FilterIndex;
use AwStudio\ModelIndex\Concerns\SearchIndex;
use AwStudio\ModelIndex\Concerns\PaginateIndex;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class IndexQueryBuilder
{
    use FilterIndex;
    use PaginateIndex;
    use SearchIndex;
    use SortIndex;

    protected ?string $resource = null;

    public function __construct(
        protected EloquentBuilder $query,
        protected ?Request $request = null
    ) {
        $this->request = $request ? $request : app('request');
    }

    /**
     * Get the underlying query builder instance.
     */
    public function query(): EloquentBuilder
    {
        return $this->query;
    }

    public function useResource(string $resource)
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Apply filters, sorting, eager loading, and pagination based on the request.
     *
     * @return mixed
     */
    public function get()
    {
        $this->applyRequestQuery();

        $pagination = $this->paginateFromRequest($this->request);

        if ($pagination instanceof LengthAwarePaginator) {
            return $this->returnResults($pagination);
        }

        return $this->returnResults($this->query->get());
    }
    
    public function paginate($perPage = null)
    {
        $this->applyRequestQuery();

        return $this->returnResults($this->paginateFromRequest($this->request, $perPage));
    }

    public function first()
    {
        $this->applyRequestQuery();

        return $this->returnResults($this->query->first());
    }


    public function count()
    {
        $this->applyRequestQuery();

        return $this->query->count();
    }

    public function applyRequestQuery()
    {
        $this->filterFromRequest($this->request);

        $this->sortFromRequest($this->request);

        $this->searchFromRequest($this->request);
    }

    public function returnResults($results)
    {
        return $this->resource
            ? $this->resource::collection($results)
            : $results;
    }

    public function __call($name, $arguments)
    {
        $this->query->$name(...$arguments);

        return $this;
    }
}
