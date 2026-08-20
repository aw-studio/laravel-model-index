<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

/**
 * @mixin \AwStudio\ModelIndex\IndexQueryBuilder
 */
trait PaginateIndex
{
    protected $pageName = 'page';

    protected $perPage = 10;

    /**
     * The maximum page size a request may ask for.
     *
     * `null` means unbounded, which is the default so that existing consumers
     * legitimately requesting large pages keep working. Public index endpoints
     * should set a ceiling via `maxPerPage()` — an unbounded `perPage` is both
     * a denial-of-service lever and a bulk-extraction one.
     *
     * @var int|null
     */
    protected $maxPerPage = null;

    public function pageName(string $pageName)
    {
        $this->pageName = $pageName;

        return $this;
    }

    /**
     * Set the maximum page size this index will honour.
     *
     * @return $this
     */
    public function maxPerPage(int $max)
    {
        $this->maxPerPage = $max;

        return $this;
    }

    /**
     * Clamp a requested page size into the allowed range.
     *
     * Values that are not a positive integer fall back to the default page
     * size rather than being passed through to the paginator, where `0` and
     * negative numbers behave inconsistently across drivers.
     *
     * @param  mixed  $perPage
     * @return int
     */
    protected function normalizePerPage($perPage)
    {
        $perPage = filter_var($perPage, FILTER_VALIDATE_INT);

        if ($perPage === false || $perPage < 1) {
            $perPage = $this->perPage;
        }

        if ($this->maxPerPage !== null) {
            $perPage = min($perPage, $this->maxPerPage);
        }

        return $perPage;
    }

    public function paginateFromRequest(Request $request, $perPage = null)
    {
        if (($request->has('page') || $request->has('perPage')) == false && $perPage === null) {
            return $this;
        }

        $perPage = $this->normalizePerPage($perPage ?? $request->get('perPage', $this->perPage));

        return $this->query()
            ->paginate($perPage, ['*'], $this->pageName)
            ->withQueryString();
    }
}
