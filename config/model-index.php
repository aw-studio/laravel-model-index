<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default page size
    |--------------------------------------------------------------------------
    |
    | Used by `paginate()` when the request does not specify `perPage` and no
    | page size is passed explicitly.
    |
    */

    'per_page' => 10,

    /*
    |--------------------------------------------------------------------------
    | Maximum page size
    |--------------------------------------------------------------------------
    |
    | Requests asking for more than this are clamped to it. An unbounded page
    | size is both a denial-of-service lever and a bulk-extraction one, so a
    | ceiling is strongly recommended on any publicly reachable index. Set to
    | `null` to leave it unbounded.
    |
    */

    'max_per_page' => null,

    /*
    |--------------------------------------------------------------------------
    | Default sortable fields
    |--------------------------------------------------------------------------
    |
    | Applies to any index that does not call `sortable([...])`. Use `['*']` to
    | allow ordering by every column, or `[]` to require each index to declare
    | its own list.
    |
    | Ordering by a column the client was never meant to see is an inference
    | oracle: sort by a secret, observe the row order, learn about the secret.
    |
    */

    'sortable' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Default searchable fields
    |--------------------------------------------------------------------------
    |
    | Applies to any index that does not call `searchable([...])`. Use `['*']`
    | to search every column, or `[]` to require each index to declare its own
    | list.
    |
    | With `['*']` the builder matches `?search=` against every column in the
    | table. Attributes listed in the model's `$hidden` array are always
    | excluded, but anything sensitive that is not hidden is not.
    |
    */

    'searchable' => ['*'],

];
