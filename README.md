# Laravel Model Index
![image](https://github.com/user-attachments/assets/7b2c2587-6378-4a4e-a142-a692cfde7de9)

## Overview

This package provides an easy to use Query Builder to filter, sort, search, and paginate Laravel models.

## Usage

Install the package via composer:

```sh
composer require aw-studio/laravel-model-index
```

The model to which the index endpoint refers must have the following trait added:

```php
use AwStudio\ModelIndex\Traits\HasIndexQuery;

class MyModel extends EloquentModel {

    use HasIndexQuery;

}
```

### Filtering

The Index Query Builder supports filtering from query parameters nearly out of the box. All you have to do is to configure which model attributes are filterable.
This can be done when using the index in the controller:

```php

public function index(Request $request){
    MyModel::index()
            ->filterable(['name', 'age']
            ->get();
}
```

Or if you'd like to resue the Index multiple times and always have the same filter options enabled, you may add a `filterable` property or method to the Model which should return an array attribute names.


Filtering the index list is now as simple as adding the query parameter “filter” to the URL with the attribute name as the key and a value. For example:

```sh
http://localhost?filter[name]=John

// or

http://localhost?filter[age]=30
```

#### Advanced Filtering

For more sophisticated filters, you may use operators and logical operators in the query parameters. For example:

```sh
http://localhost?filter[age][$gte]=18&filter[age][$lte]=30
```

The following operators are supported:

- `$eq` - Equal
- `$ne` - Not equal
- `$gt` - Greater than
- `$gte` - Greater than or equal
- `$lt` - Less than
- `$lte` - Less than or equal
- `$in` - In
- `$notIn` - Not in
- `$contains` - Contains
- `$notContains` - Not contains
- `$between` - Between
- `$startsWith` - Starts with
- `$endsWith` - Ends with
- `$null` - Null
- `$notNull` - Not null

Logical operators can be used to combine filters. For example:

```sh
http://localhost?filter[$or][0][name][$contains]=John&filter[$or][1][name][$contains]=Doe
```

The following logical operators are supported:

- `$and`
- `$or`

#### Custom Filters

By default filter keys are supposed to match the column names of the model.
You can also define custom filter keys by using the `filter` method on the Index query and define custom callbacks for the filter.
This also allows you to use model scopes for filtering or more complex queries e.g. in related models.

```php
public function index(Request $request)
{
    // http://localhost?filter[popular]=true
    return User::index()
        ->filter('popular', fn($query, $value) => $query->popular())
        ->get();


    // http://localhost?filer[user.name]=John
    return Post::index()
        ->filter('user.name', function ($query, $value) {
            $query->whereHas('user', fn($query) => $query->where('name', $value));
        })
        ->get();
}
```

### Sorting

You can sort the index using query parameters. For example:

```sh
http://localhost?sort=name
```

You can also sort in descending order:

```sh
http://localhost?sort=-name

// or

http://localhost?sort=name:desc
```

Multiple columns can be sorted by separating them with a comma:

```sh
http://localhost?sort=name,-age
```

### Searching

You can search the index using query parameters. For example:

```sh
http://localhost?search=John
```

This will search all configured columns for the term "John".

#### Configuring Searchable Columns

To configure which columns should be searchable, use the `searchable` method on the
Index query:

```php
public function index(Request $request)
{
    return User::index()
        ->searchable(['name', 'email'])
        ->get();
}
```

### Pagination

You can automatically paginate the index using query parameters by adding the `page` query parameter to the URL or setting a per_page value in the query parameters. For example:

```sh
http://localhost?page=2

// or

http://localhost?perPage=10
```

## License

This project is licensed under the MIT License. See the LICENSE file for details.
