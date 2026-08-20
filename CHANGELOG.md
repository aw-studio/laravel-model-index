# Changelog

All notable changes to `aw-studio/laravel-model-index` are documented here.

## 1.0.0

First release with a stability commitment. See [UPGRADE.md](UPGRADE.md) for
migration steps.

### Behaviour change

- **`get()` always returns a Collection; `paginate()` always returns a
  paginator.** `get()` previously returned a paginator when the request happened
  to carry `page` or `perPage`, so the caller's query string decided the response
  envelope and every consumer had to handle both shapes. Whether an endpoint
  paginates is now the endpoint's decision. Also fixes `paginate()` with no
  arguments returning the builder itself.

- **Sorting and searching deny by default.** `sortable` and `searchable` default
  to `[]` rather than `['*']`, matching filtering. Set them to `['*']` in
  `config/model-index.php` to restore the previous behaviour. `max_per_page`
  defaults to 100 rather than unbounded.

- **`IndexQueryBuilder::defaultSortable()` and `defaultSearchable()` are
  removed** in favour of `config/model-index.php`. The static properties they
  wrote to persisted across requests under Octane.

- **Unknown filter operators now throw instead of silently becoming `=`.**
  `transformOperator()` previously fell back to equality for any operator it did
  not recognise, and passed the value through unwrapped. A request such as
  `filter[name][$containsi]=mar` therefore became `where name = 'mar'` and
  returned an empty list — indistinguishable from "there is no matching data",
  which sent people looking in the database rather than at the query.

  Such a request now throws an `InvalidArgumentException` naming the operator
  and the field. The guard in `FilterIndex::applyFilters()` is retained as
  defence in depth; it was previously unreachable, because the fallback had
  already rewritten the unknown operator by the time it ran.

  Requests that relied on the old behaviour were already returning wrong
  results. The four case-insensitive operators that most commonly hit this path
  are implemented in this same release, so no caller is left with a request that
  used to return something and now errors.

- **Hidden columns are no longer searched.** With the default
  `searchable(['*'])` the builder expanded to every column in the table,
  including `password` and `remember_token`. Because the row count differs on a
  match, that made `?search=` a usable prefix oracle for extracting a secret
  (`?search=SEC` → 1 row, `?search=SX` → 0 rows). `$hidden` gave no protection,
  since it only affects serialization and never `WHERE` clauses.

  The `['*']` expansion now subtracts the model's `$hidden` attributes. Columns
  named explicitly in `searchable([...])` are still searched — only the wildcard
  is filtered. This is breaking only for a search that was matching a hidden
  column, which is the defect itself.

- **Searching an index with an empty searchable set now throws.** Previously the
  term was silently dropped and the full unfiltered list came back, which reads
  as "everything matched". `searchable([])` is therefore no longer a way to turn
  search off; drop the parameter upstream instead. A registered custom search
  callback still makes an index searchable.

- **All operators on a field are now applied, not just the first.**
  `parseCondition()` returned from inside its loop, so
  `filter[age][$gte]=18&filter[age][$lte]=30` — the operator range example in
  this package's own README — applied `>= 18` and silently discarded the upper
  bound. Affected requests were returning too many rows; they now return the
  correct, smaller set.

  Conditions take the boolean of the group they are in, so multiple operators
  AND at the top level and OR inside an `$or` branch. Use `$between` for a range
  inside an `$or`.

  `parseCondition()` (protected) now returns a *list* of `[field, operator,
  value]` triples rather than a single triple. Relevant only if you subclass and
  override it.

### Added

- Case-insensitive filter operators `$eqi`, `$nei`, `$containsi` and
  `$notContainsi`. These were already advertised by the `@aw-studio/nuxt-laravel`
  client but had no server-side implementation. Implemented portably as
  `LOWER(column) <op> LOWER(?)` rather than PostgreSQL-only `ILIKE`. Note that
  MySQL's default collation already ignores case, so the difference is only
  observable on PostgreSQL and SQLite.
- `maxPerPage(int $max)` on the index builder, clamping the page size a request
  may ask for. The default remains **unbounded** so existing consumers are
  unaffected; public index endpoints should set a ceiling, since an unbounded
  `perPage` is both a denial-of-service and a bulk-extraction lever.
- `IndexQueryBuilder::defaultSearchable(array $fields)` for requiring every
  index to declare its searchable columns. The default stays `['*']`.
- `IndexQueryBuilder::defaultSortable(array $fields)` for opting into a strict
  application-wide sort allowlist. Sorting still defaults to `['*']` — allow
  everything — for backwards compatibility. Ordering by a column the client was
  never meant to see is an inference oracle, so public indexes should either
  call `sortable([...])` or opt into `defaultSortable([])` during boot.
- Laravel 13 support: `illuminate/database` and `illuminate/http` now allow
  `^11.0|^12.0|^13.0`. Purely additive — Laravel 11 and 12 remain supported.
- **Relation filtering.** `filterable(['user.name'])` resolves through
  `whereHas`, including nested relations, so a custom callback is no longer
  needed for every relation field. A dotted field is only treated as a relation
  when its first segment is one, so filtering on a manually joined
  `table.column` is unaffected.
- **Cursor pagination** via `cursorPaginate()`: keyset-based, with no `COUNT`
  over the full result set and no `OFFSET` degradation on deep pages.
- A publishable config file (`per_page`, `max_per_page`, `sortable`,
  `searchable`) and a `ModelIndexServiceProvider`.
- `cursorName()` for renaming the cursor query parameter.

### Fixed

- `$between` now accepts the comma-separated form
  (`filter[age][$between]=18,30`). It previously raised a `TypeError`, because
  the string was passed straight to `whereBetween()`, which requires an array.
  Bounds are trimmed, and anything other than exactly two bounds throws a
  descriptive `InvalidArgumentException`.
- `perPage` values that are not positive integers (`0`, `-5`, `abc`) now fall
  back to the default page size instead of reaching the paginator, where they
  behaved inconsistently.
- Corrected the `@mixin` docblock in `PaginateIndex`, which pointed at
  `\App\IndexBuilder\IndexQueryBuilder` — an application namespace that does not
  exist in this package — and so defeated static analysis.

### Decided

- `$between` bounds are **not** cast. Casting to `int` (a long-standing `TODO`
  in `transformValue()`) would fix the numeric case but corrupt date bounds such
  as `2026-01-01`, which are a first-class use of the operator. Numeric strings
  bind correctly as-is, so no cast is the right answer for both. The commented-out
  code has been removed and the decision is covered by a test.

### Internal

- Widened the dev requirements to `orchestra/testbench ^9.9|^10.0|^11.0` and
  `pestphp/pest ^3.7|^4.0|^5.0`. The suite could not be installed at all on
  `testbench ^9.9`, because every resolvable version pinned a Laravel 11 release
  blocked by Composer's security-advisory policy. Development now runs on
  Laravel 13 / Testbench 11 / Pest 5. Dev-only; no consumer impact.
- Added a CI workflow (there was none): a PHP 8.3/8.4 × Laravel 12/13 test
  matrix, plus a non-gating Pint style report. Laravel 11 is intentionally
  absent from the matrix — its releases cannot currently be installed, so
  support for it is declared but not claimed to be tested.
