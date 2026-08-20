<?php

namespace AwStudio\ModelIndex\Concerns;

use Illuminate\Http\Request;

/**
 * @mixin \AwStudio\ModelIndex\IndexQueryBuilder
 */
trait PaginateIndex
{
    protected $pageName = 'page';

    protected $cursorName = 'cursor';

    /**
     * Page size for this builder instance.
     *
     * `null` defers to the `model-index.per_page` config value.
     *
     * @var int|null
     */
    protected $perPage = null;

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

    /**
     * Get the default page size.
     */
    protected function defaultPerPage(): int
    {
        return $this->perPage ?? (int) config('model-index.per_page', 10);
    }

    /**
     * Get the page-size ceiling, or null when unbounded.
     */
    protected function resolveMaxPerPage(): ?int
    {
        $max = $this->maxPerPage ?? config('model-index.max_per_page');

        return $max === null ? null : (int) $max;
    }

    public function pageName(string $pageName)
    {
        $this->pageName = $pageName;

        return $this;
    }

    /**
     * Rename the cursor query parameter used by cursor pagination.
     *
     * @return $this
     */
    public function cursorName(string $cursorName)
    {
        $this->cursorName = $cursorName;

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
            $perPage = $this->defaultPerPage();
        }

        $max = $this->resolveMaxPerPage();

        if ($max !== null) {
            $perPage = min($perPage, $max);
        }

        return $perPage;
    }

    /**
     * Paginate the query, taking the page size from the argument, then the
     * request, then the configured default.
     *
     * @param  int|null  $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateFromRequest(Request $request, $perPage = null)
    {
        $perPage = $this->normalizePerPage($perPage ?? $request->get('perPage', $this->defaultPerPage()));

        return $this->query()
            ->paginate($perPage, ['*'], $this->pageName)
            ->withQueryString();
    }

    /**
     * Cursor-paginate the query.
     *
     * Cursor pagination is keyset-based: it does not COUNT the full result set
     * and does not degrade on deep pages the way OFFSET does, which matters on
     * large lists. The trade-off is no total or last page, and no jumping to an
     * arbitrary page number - so it suits infinite scroll rather than a numbered
     * pager.
     *
     * @param  int|null  $perPage
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    public function cursorPaginateFromRequest(Request $request, $perPage = null)
    {
        $perPage = $this->normalizePerPage($perPage ?? $request->get('perPage', $this->defaultPerPage()));

        return $this->query()
            ->cursorPaginate($perPage, ['*'], $this->cursorName)
            ->withQueryString();
    }
}
