# Laravel Model Index
![image](https://github.com/user-attachments/assets/7b2c2587-6378-4a4e-a142-a692cfde7de9)

Filterable, sortable, searchable and paginated index endpoints for Eloquent models,
driven entirely by query parameters.

```php
return Product::index()
    ->filterable(['name', 'price', 'category_id'])
    ->sortable(['name', 'price', 'created_at'])
    ->searchable(['name', 'sku'])
    ->maxPerPage(100)
    ->get();
```

```
GET /api/products?filter[price][$lte]=100&filter[name][$containsi]=shirt&sort=-created_at&page=2&perPage=25
```

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Return values](#return-values)
- [Filtering](#filtering)
  - [Operators](#operators)
  - [Logical operators](#logical-operators)
  - [Custom filters](#custom-filters)
  - [Configuring filterable fields on the model](#configuring-filterable-fields-on-the-model)
- [Sorting](#sorting)
- [Searching](#searching)
- [Pagination](#pagination)
- [Builder reference](#builder-reference)
- [Securing a public endpoint](#securing-a-public-endpoint)
- [Frontend client](#frontend-client)
- [Testing](#testing)

---

## Requirements

| | Supported |
| --- | --- |
| PHP | `8.2+` (Laravel 13 itself requires `8.3+`) |
| Laravel | `11.x`, `12.x`, `13.x` |

CI covers PHP 8.2/8.3/8.4 against Laravel 12, and 8.3/8.4 against Laravel 13. Laravel 11
is still allowed by the version constraint but is not exercised in CI, because every 11.x
release is currently blocked by Composer's security-advisory policy and cannot be
installed at all.

## Installation

```sh
composer require aw-studio/laravel-model-index
```

Optionally publish the config:

```sh
php artisan vendor:publish --tag=model-index-config
```

```php
// config/model-index.php
return [
    'per_page' => 10,
    'max_per_page' => 100,   // null to disable the ceiling
    'sortable' => [],        // ['*'] to allow every column
    'searchable' => [],      // ['*'] to search every column
];
```

Add the trait to any model you want an index endpoint for:

```php
use AwStudio\ModelIndex\Traits\HasIndexQuery;

class Product extends Model
{
    use HasIndexQuery;
}
```

That gives you a static `index()` returning an `IndexQueryBuilder`, which reads the
current request:

```php
public function index()
{
    return Product::index()
        ->filterable(['name', 'price'])
        ->get();
}
```

## Return values

The builder deliberately returns different things depending on the request, so it is
worth knowing which you get:

| Call | Returns |
| --- | --- |
| `get()` | `Illuminate\Support\Collection`, always |
| `paginate($perPage = null)` | `LengthAwarePaginator`, always |
| `cursorPaginate($perPage = null)` | `CursorPaginator`, always |
| `first()` | Model or `null` |
| `count()` | `int` |

Return types do not depend on the request. Whether an endpoint paginates is the
endpoint's decision; which page and how large is the caller's. `get()` still
applies filters, sorting and search — it just ignores `page` and `perPage`.

Wrap results in an API Resource with `useResource()`:

```php
Product::index()->useResource(ProductResource::class)->get();
```

## Filtering

**Filtering is deny-by-default.** Nothing is filterable until you say so, either per
call or on the model.

```php
Product::index()->filterable(['name', 'price'])->get();
```

```sh
GET /products?filter[name]=Shirt
GET /products?filter[price]=100
```

Filtering by a field that is not allowed throws an `InvalidArgumentException`.

Comma-separated values become an `IN` filter:

```sh
GET /products?filter[name]=Shirt,Hoodie
```

### Operators

Operators are nested under the field:

```sh
GET /products?filter[price][$gte]=18&filter[stock][$gt]=0
```

| Operator | Meaning |
| --- | --- |
| `$eq` | Equal |
| `$eqi` | Equal, case-insensitive |
| `$ne` | Not equal |
| `$nei` | Not equal, case-insensitive |
| `$gt` | Greater than |
| `$gte` | Greater than or equal |
| `$lt` | Less than |
| `$lte` | Less than or equal |
| `$in` | In list |
| `$notIn` | Not in list |
| `$contains` | Contains |
| `$notContains` | Does not contain |
| `$containsi` | Contains, case-insensitive |
| `$notContainsi` | Does not contain, case-insensitive |
| `$between` | Between two bounds |
| `$startsWith` | Starts with |
| `$endsWith` | Ends with |
| `$null` | Is null |
| `$notNull` | Is not null |

An operator outside this list throws an `InvalidArgumentException` naming the operator
and the field. It is never silently treated as equality.

> [!NOTE]
> **Case-insensitive operators.** The `i`-suffixed operators compile to
> `LOWER(column) <op> LOWER(?)`, which is portable across MySQL, PostgreSQL and SQLite.
> MySQL's default collation (`utf8mb4_unicode_ci`) already ignores case, so on MySQL
> they behave identically to their plain counterparts — the difference only shows up on
> PostgreSQL and SQLite. Do not "verify" case-sensitivity against MySQL alone.

`$between` takes exactly two bounds, comma-separated or as an array:

```sh
GET /products?filter[price][$between]=10,99
GET /orders?filter[created_at][$between][0]=2026-01-01&filter[created_at][$between][1]=2026-12-31
```

Bounds are passed through without casting, so date ranges work as well as numeric ones.
Anything other than exactly two bounds throws.

Several operators may be combined on one field. Each becomes its own condition,
ANDed together:

```sh
GET /products?filter[price][$gte]=18&filter[price][$lte]=30
# where price >= 18 and price <= 30
```

> [!NOTE]
> Conditions take the boolean of the group they sit in, so two operators inside
> a single `$or` branch are ORed, not ANDed — `$or[0][age][$gte]=18&$or[0][age][$lte]=30`
> means `age >= 18 or age <= 30`. Use `$between` for a range inside an `$or`.

### Logical operators

`$and` and `$or` groups can be nested:

```sh
GET /products?filter[$or][0][name][$contains]=Shirt&filter[$or][1][name][$contains]=Hoodie
```

```php
// equivalent to: where name like %Shirt% or name like %Hoodie%
```

### Custom filters

Filter keys do not have to be columns. Register a callback to map a key onto a scope,
a relation, or anything else:

```php
public function index()
{
    // GET /users?filter[popular]=true
    return User::index()
        ->filter('popular', fn ($query, $value) => $query->popular())
        ->get();
}

// GET /posts?filter[user.name]=John
return Post::index()
    ->filter('user.name', function ($query, $value) {
        $query->whereHas('user', fn ($q) => $q->where('name', $value));
    })
    ->get();
```

Custom filter keys bypass the `filterable()` allowlist — registering the callback *is*
the opt-in.

### Filtering on relations

Dot notation filters through an Eloquent relation, resolved with `whereHas`:

```php
Post::index()->filterable(['user.name', 'user.email'])->get();
```

```sh
GET /posts?filter[user.name]=John
GET /posts?filter[user.name][$containsi]=joh
```

Nested relations work too (`user.company.name`). Relation fields go through the
same allowlist as ordinary columns, so they must be listed in `filterable()`.

A dotted field is only treated as a relation when its first segment is an actual
relation on the model — so if you join manually and filter on `table.column`,
that still resolves as a qualified column reference.

### Configuring filterable fields on the model

To reuse the same configuration everywhere, put it on the model instead. A method takes
precedence over a property:

```php
class Product extends Model
{
    use HasIndexQuery;

    public function filterable(): array
    {
        return ['name', 'price', 'category_id'];
    }

    // or:
    public $filterable = ['name', 'price'];
}
```

## Sorting

```sh
GET /products?sort=name          # ascending
GET /products?sort=-name         # descending
GET /products?sort=name:desc     # descending, alternative syntax
GET /products?sort=name,-price   # multiple columns
```

**Sorting is deny-by-default.** Declare what an index may be ordered by:

```php
Product::index()->sortable(['name', 'price', 'created_at'])->get();
```

Sorting by a field outside the allowlist throws an `InvalidArgumentException`.

Ordering by a column the client was never meant to see is an inference oracle: sort
by a secret, observe the row order, learn about the secret. To opt out of the safe
default application-wide, set `sortable` to `['*']` in `config/model-index.php`.

Custom sort keys work like custom filters, and receive the direction:

```php
Product::index()
    ->sort('popularity', fn ($query, $direction) => $query->orderBy('sales_count', $direction))
    ->get();
```

## Searching

```sh
GET /products?search=shirt
```

**Searching is deny-by-default.** Declare what an index may be searched on:

```php
User::index()->searchable(['name', 'email'])->get();
```

Related columns use dot notation and are matched with `orWhereHas`:

```php
Post::index()->searchable(['title', 'user.name'])->get();
```

Custom search callbacks are merged into the searchable set:

```php
Product::index()
    ->search('sku_prefix', fn ($query, $term) => $query->orWhere('sku', 'like', "{$term}%"))
    ->get();
```

#### The `['*']` wildcard

Setting `searchable` to `['*']` — per index, or as the config default — expands to
every column in the table. Attributes in the model's `$hidden` array are always
excluded, so `password` and `remember_token` are never matched, but anything
sensitive that is *not* hidden is. Prefer an explicit list.

Searching an index whose searchable set is empty throws an
`InvalidArgumentException` rather than silently returning the unfiltered list.
Registering a custom search callback is enough to make an index searchable, even
with an otherwise empty list.

## Pagination

```sh
GET /products?page=2
GET /products?perPage=25
```

Default page size is 10. Rename the page parameter with `pageName()`:

```php
Product::index()->pageName('p')->get();   // GET /products?p=2
```

`perPage` is capped at **100** by default. Requests above the ceiling are clamped to
it, and a `perPage` that is not a positive integer (`0`, `-5`, `abc`) falls back to
the default page size. Override per index or in config:

```php
Product::index()->maxPerPage(250)->paginate();
```

Set `max_per_page` to `null` to remove the ceiling entirely — but note that an
unbounded page size is both a denial-of-service lever and a bulk-extraction one.

### Cursor pagination

For large lists, `cursorPaginate()` is keyset-based: it does not `COUNT` the full
result set and does not degrade on deep pages the way `OFFSET` does.

```php
Product::index()->sortable(['created_at'])->cursorPaginate();
```

```sh
GET /products?perPage=25
GET /products?perPage=25&cursor=eyJpZCI6MjUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0
```

The trade-off is no `total` and no `last_page`, and no jumping to an arbitrary
page number — so it suits infinite scroll rather than a numbered pager. Rename
the cursor parameter with `cursorName()`.

## Builder reference

| Method | Purpose |
| --- | --- |
| `filterable(array)` | Allowlist of filterable fields |
| `filter(string, callable)` | Register a custom filter key |
| `sortable(array)` | Allowlist of sortable fields |
| `sort(string, callable)` | Register a custom sort key |
| `IndexQueryBuilder::defaultSortable(array)` | Application-wide sort default |
| `searchable(array)` | Columns searched by `?search=` |
| `search(string, callable)` | Register a custom search key |
| `maxPerPage(int)` | Ceiling for `?perPage=` |
| `pageName(string)` | Rename the page query parameter |
| `cursorName(string)` | Rename the cursor query parameter |
| `useResource(string)` | Wrap results in an API Resource |
| `tap(callable)` | Run a callback against the builder |
| `query()` | Get the underlying Eloquent builder |
| `applyRequestQuery()` | Apply filter/sort/search without fetching |
| `get()` / `paginate()` / `cursorPaginate()` / `first()` / `count()` | Execute |

Any other method is proxied to the underlying Eloquent builder, so you can mix in
ordinary query methods:

```php
Product::index()->with('category')->where('active', true)->get();
```

Note that the proxy always returns the `IndexQueryBuilder`, discarding the return value
of the proxied call.

## Securing a public endpoint

All three allowlists deny by default, and page size is capped:

| Concern | Default | |
| --- | --- | --- |
| Filtering | `[]` — deny all | ✅ |
| Sorting | `[]` — deny all | ✅ |
| Searching | `[]` — deny all | ✅ |
| `perPage` | capped at 100 | ✅ |

So an index exposes nothing until you say what it exposes:

```php
Model::index()
    ->filterable(['name', 'status'])
    ->sortable(['name', 'created_at'])
    ->searchable(['name', 'sku'])
    ->paginate();
```

Each list can be relaxed globally in `config/model-index.php` if you would rather
opt out than opt in — but the shipped defaults are the safe ones.

## Frontend client

[`@aw-studio/nuxt-laravel`](https://github.com/aw-studio/nuxt-laravel) provides
`useLaravelIndex`, which serializes exactly the query-string shape documented here.

| This package | Client |
| --- | --- |
| `1.x` | `@aw-studio/nuxt-laravel` `1.x` |

The two are released as a pair. Because the coupling is an untyped query string,
mixing majors is not supported.

The two packages are coupled **only by the query string** — there is no generated client
and no shared schema. An operator that exists on one side but not the other fails at
runtime, so the operator table above and the one in the client's README must be changed
together.

## Testing

```sh
composer test                                      # Pest, via Testbench on SQLite
vendor/bin/pest tests/Feature/FilterIndexTest.php  # single file
vendor/bin/pest --filter "case-insensitive"        # single test
vendor/bin/pint                                    # code style
```

## License

This project is licensed under the MIT License. See the LICENSE file for details.
