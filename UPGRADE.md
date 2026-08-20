# Upgrade Guide

## 0.1.x → 1.0.0

1.0.0 is the first release with a stability commitment, and it takes the
opportunity to fix things that could not be fixed afterwards without a 2.0.

Most of the changes below turn something that **silently produced wrong results**
into something that fails loudly, or close a default that was unsafe. Read the
first two sections carefully — the rest is mechanical.

Nothing here reaches you until you change your version constraint. `^0.1.3`
resolves to `>=0.1.3 <0.2.0`, so 0.x installs are unaffected by this release.

---

## 1. Defaults are now deny-by-default

**This is the change most likely to break your app, and it will break loudly.**

Sorting and searching used to allow every column. They now deny everything until
an index says what it exposes, matching how filtering has always worked.

```php
// Before — worked, and allowed ordering by any column in the table
return Product::index()->filterable(['name'])->get();

// After — declare what may be sorted and searched
return Product::index()
    ->filterable(['name'])
    ->sortable(['name', 'created_at'])
    ->searchable(['name', 'sku'])
    ->get();
```

A request that sorts or searches an index which has not declared its list now
throws `InvalidArgumentException`. Requests without `?sort=` or `?search=` are
unaffected.

**To restore the old behaviour wholesale**, publish the config and open both
lists back up:

```sh
php artisan vendor:publish --tag=model-index-config
```

```php
// config/model-index.php
'sortable' => ['*'],
'searchable' => ['*'],
```

That is a legitimate short-term migration step, but the reason for the change is
worth knowing before you take it. Ordering by a column the client was never meant
to see is an inference oracle — sort by a secret, observe the row order, learn
about the secret. Searching was worse: it expanded to every column in the table,
so `?search=` matched against password hashes and remember tokens, and the row
count made that a working prefix oracle (`?search=SEC` → 1 row, `?search=SX` → 0
rows). Hidden columns are now excluded from the `['*']` expansion regardless, but
anything sensitive that is *not* in the model's `$hidden` still is not.

`perPage` is also capped at **100** by default. Set `max_per_page` to `null` to
remove the ceiling.

## 2. `get()` and `paginate()` return one type each

`get()` used to return a paginator when the request happened to carry `page` or
`perPage`, and a collection otherwise — so the **caller's query string decided
your response envelope**.

```php
// Before
Product::index()->get();   // Collection, or LengthAwarePaginator if ?page=… was sent

// After
Product::index()->get();        // always a Collection
Product::index()->paginate();   // always a LengthAwarePaginator
```

If an endpoint relied on `get()` paginating when the client asked, call
`paginate()` instead:

```diff
-return Product::index()->filterable(['name'])->get();
+return Product::index()->filterable(['name'])->paginate();
```

`get()` still applies filters, sorting and search — it just ignores `page` and
`perPage`. Which page and how large is still the caller's choice; whether the
endpoint paginates at all is now yours.

This also fixes `paginate()` with no arguments and no pagination params
returning the builder itself, which blew up outright when combined with
`useResource()`.

## 3. Unknown filter operators throw

An operator the package does not recognise used to be coerced to `=`, with the
value passed through unwrapped — so `filter[name][$containsi]=mar` became
`where name = 'mar'` and returned an empty list. That reads as "no matches" and
sends people looking in the data rather than at the query.

```php
// Now throws InvalidArgumentException naming the operator and the field
GET /products?filter[name][$typo]=mar
```

The four case-insensitive operators that most commonly hit this path —
`$eqi`, `$nei`, `$containsi`, `$notContainsi` — are implemented as of this
release, so a request using them now works rather than erroring.

## 4. All operators on a field are applied

`filter[age][$gte]=18&filter[age][$lte]=30` used to apply `>= 18` and silently
discard the upper bound, returning too many rows. Both are now applied.

**Affected queries start returning fewer, correct rows.** If you worked around
this with `$between`, that still works and needs no change.

Conditions take the boolean of the group they sit in, so multiple operators AND
at the top level and OR inside an `$or` branch. For a range inside an `$or`, use
`$between`.

## 5. `searchable([])` no longer disables search

An empty searchable set used to drop the search term and return the whole
unfiltered list, which reads as "everything matched". It now throws.

```diff
-Product::index()->searchable([])->get();   // was: search silently ignored
+Product::index()->get();                    // omit the ?search= param upstream instead
```

A registered custom search callback still makes an index searchable, even with
an otherwise empty list.

## 6. `defaultSortable()` / `defaultSearchable()` are removed

These static methods wrote to static properties that persisted across requests
under Octane. Use the config file instead:

```diff
-// AppServiceProvider::boot()
-IndexQueryBuilder::defaultSortable([]);
-IndexQueryBuilder::defaultSearchable([]);
+// config/model-index.php
+'sortable' => [],
+'searchable' => [],
```

Both already default to `[]`, so in most cases the call simply goes away.

## 7. `$between` requires exactly two bounds

Anything other than two bounds now throws instead of producing a driver-level
error. The comma-separated form also works for the first time —
`filter[age][$between]=18,30` previously raised a `TypeError`.

Bounds are **not** cast, so date ranges work as well as numeric ones.

## 8. If you subclass the builder

`parseCondition()` (protected) returns a **list** of `[field, operator, value]`
triples rather than a single triple, since one field may carry several
operators. Only relevant if you override it.

---

## New in 1.0.0

None of these require action, but they may replace code you are currently
hand-writing:

- **Relation filtering** — `filterable(['user.name'])` resolves through
  `whereHas`, including nested relations. Previously this needed a custom filter
  callback for every relation field.
- **Cursor pagination** — `cursorPaginate()` is keyset-based: no `COUNT` over
  the full result set, no `OFFSET` degradation on deep pages. Suits infinite
  scroll rather than a numbered pager.
- **Case-insensitive operators** — `$eqi`, `$nei`, `$containsi`,
  `$notContainsi`. Note that MySQL's default collation already ignores case, so
  the difference shows up on PostgreSQL and SQLite.
- **`maxPerPage()`** and **`cursorName()`** builder methods.
- **Config file** — `per_page`, `max_per_page`, `sortable`, `searchable`.
- **Laravel 13 support** — the constraint is now `^11.0|^12.0|^13.0`.

## Client compatibility

`@aw-studio/nuxt-laravel` **1.x** pairs with this release. The two packages are
coupled only by the query string, so mixing majors is not supported — see that
package's own `UPGRADE.md`.
