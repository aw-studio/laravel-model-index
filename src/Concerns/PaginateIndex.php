<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

/**
 * @mixin \App\IndexBuilder\IndexQueryBuilder
 */
trait PaginateIndex
{
    protected $pageName = 'page';

    protected $perPage = 10;

    public function pageName(string $pageName)
    {
        $this->pageName = $pageName;

        return $this;
    }

    public function paginateFromRequest(Request $request, $perPage = null)
    {
        if (($request->has('page') || $request->has('perPage')) == false && $perPage === null) {
            return $this;
        }

        $perPage = $perPage ?? $request->get('perPage', $this->perPage);

        return $this->query()
            ->paginate($perPage, ['*'], $this->pageName)
            ->withQueryString();
    }
}
