<?php

use AwStudio\ModelIndex\IndexQueryBuilder;
use Workbench\App\Models\TestModel;
use Workbench\App\Models\User;

test('It searches the records columns', function () {
    TestModel::factory()->count(5)->create();
    TestModel::factory(['name' => 'ustupid'])->create();

    makeRequest('http://localhost?search=ustupid');

    expect(TestModel::index()->get()->count())->toBe(1);
});

test('It searches all columns', function () {
    TestModel::factory()->count(5)->create();
    TestModel::factory(['age' => 10000])->create();

    makeRequest('http://localhost?search=1000');

    expect(TestModel::index()->get()->count())->toBe(1);
});

test('It can use custom search callbacks', function () {
    TestModel::factory(['name' => 'John Doe'])->create();
    TestModel::factory(['name' => 'Jane Smith'])->create();
    TestModel::factory(['name' => 'Bob Johnson'])->create();

    makeRequest('http://localhost?search=john');

    $index = TestModel::index()
        ->search('full_name', function ($query, $search) {
            $query->orWhere('name', 'like', "%{$search}%");
        })
        ->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('name')->toArray())->toContain('John Doe', 'Bob Johnson');
});

/*
|--------------------------------------------------------------------------
| searchable() — restricting which columns are searched
|--------------------------------------------------------------------------
|
| These cover the documented `searchable()` API, which had no test coverage at
| all despite being the only thing standing between `?search=` and every column
| in the table. See the "all columns" characterization test at the bottom.
|
*/

test('it only searches the columns declared via searchable', function () {
    TestModel::factory(['name' => 'Zaphod', 'color' => 'plain'])->create();
    TestModel::factory(['name' => 'plain', 'color' => 'Zaphod'])->create();

    makeRequest('http://localhost?search=Zaphod');

    $index = TestModel::index()->searchable(['name'])->get();

    expect($index->count())->toBe(1);
    expect($index->first()->color)->toBe('plain');
});

test('it does not match columns outside the searchable allowlist', function () {
    TestModel::factory(['name' => 'plain', 'color' => 'Zaphod'])->create();

    makeRequest('http://localhost?search=Zaphod');

    expect(TestModel::index()->searchable(['name'])->get()->count())->toBe(0);
});

test('it searches across multiple declared columns', function () {
    TestModel::factory(['name' => 'Zaphod', 'title' => 'nope'])->create();
    TestModel::factory(['name' => 'nope', 'title' => 'Zaphod'])->create();
    TestModel::factory(['name' => 'nope', 'title' => 'nope', 'color' => 'Zaphod'])->create();

    makeRequest('http://localhost?search=Zaphod');

    expect(TestModel::index()->searchable(['name', 'title'])->get()->count())->toBe(2);
});

test('it searches partial matches', function () {
    TestModel::factory(['name' => 'Beeblebrox'])->create();
    TestModel::factory(['name' => 'Dent'])->create();

    makeRequest('http://localhost?search=eeble');

    expect(TestModel::index()->searchable(['name'])->get()->count())->toBe(1);
});

test('it returns everything when no search term is given', function () {
    TestModel::factory()->count(3)->create();

    makeRequest('http://localhost');

    expect(TestModel::index()->searchable(['name'])->get()->count())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Relation search
|--------------------------------------------------------------------------
*/

test('it searches a related model via dot notation', function () {
    $user = User::forceCreate(['name' => 'Zaphod', 'email' => 'z@example.test', 'password' => 'secret']);
    $match = TestModel::factory(['name' => 'nope'])->create();
    $match->forceFill(['user_id' => $user->id])->save();

    TestModel::factory(['name' => 'nope'])->create();

    makeRequest('http://localhost?search=Zaphod');

    $index = TestModel::index()->searchable(['user.name'])->get();

    expect($index->count())->toBe(1);
    expect($index->first()->id)->toBe($match->id);
});

test('it combines own columns and related columns in one search', function () {
    $user = User::forceCreate(['name' => 'Zaphod', 'email' => 'z@example.test', 'password' => 'secret']);
    $viaRelation = TestModel::factory(['name' => 'nope'])->create();
    $viaRelation->forceFill(['user_id' => $user->id])->save();

    TestModel::factory(['name' => 'Zaphod'])->create();
    TestModel::factory(['name' => 'nope'])->create();

    makeRequest('http://localhost?search=Zaphod');

    expect(TestModel::index()->searchable(['name', 'user.name'])->get()->count())->toBe(2);
});

test('a custom search callback is merged into the searchable set', function () {
    TestModel::factory(['name' => 'nope', 'title' => 'Zaphod'])->create();
    TestModel::factory(['name' => 'Zaphod', 'title' => 'nope'])->create();
    TestModel::factory(['name' => 'nope', 'title' => 'nope'])->create();

    makeRequest('http://localhost?search=Zaphod');

    $index = TestModel::index()
        ->searchable(['name'])
        ->search('by_title', fn ($query, $term) => $query->orWhere('title', 'like', "%{$term}%"))
        ->get();

    expect($index->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| The permissive default
|--------------------------------------------------------------------------
*/

test('the default searchable set is every column in the table', function () {
    // Characterization test, NOT an endorsement. With the shipped `['*']`
    // default the builder introspects the schema and emits a LIKE against every
    // column — on a users table that includes password and remember_token, and
    // the result count is a usable prefix oracle. This test exists so that
    // changing the default is a deliberate, visible act rather than a silent one.
    TestModel::factory(['name' => 'nope', 'color' => 'Zaphod'])->create();

    makeRequest('http://localhost?search=Zaphod');

    expect(TestModel::index()->get()->count())->toBe(1);

    $sql = TestModel::index()->applyRequestQuery()->query()->toSql();

    foreach (['"name"', '"color"', '"title"', '"age"'] as $column) {
        expect($sql)->toContain($column);
    }
});

test('an explicit searchable list narrows the permissive default', function () {
    TestModel::factory(['name' => 'nope', 'color' => 'Zaphod'])->create();

    makeRequest('http://localhost?search=Zaphod');

    expect(TestModel::index()->get()->count())->toBe(1);
    expect(TestModel::index()->searchable(['name'])->get()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Hidden columns are never searched
|--------------------------------------------------------------------------
|
| `$hidden` only affects serialization, so before this the `['*']` expansion
| matched `?search=` against password hashes and remember tokens, and the row
| count made that a usable prefix oracle. The model already declares which
| attributes must never be exposed; the wildcard expansion now honours it.
|
*/

test('it excludes hidden columns from the wildcard expansion', function () {
    // `remember_token` rather than `password` because the User model casts
    // password to 'hashed', so the literal never reaches the column.
    User::forceCreate([
        'name' => 'Alice',
        'email' => 'a@example.test',
        'password' => 'irrelevant',
        'remember_token' => 'SECRETTOKEN',
    ]);

    $request = makeRequest('http://localhost?search=SECRET');
    $builder = new IndexQueryBuilder(User::query(), $request);
    $builder->searchFromRequest($request);

    expect($builder->query()->count())->toBe(0);
});

test('it does not reference hidden columns in the generated sql', function () {
    $request = makeRequest('http://localhost?search=anything');
    $builder = new IndexQueryBuilder(User::query(), $request);
    $builder->searchFromRequest($request);

    $sql = $builder->query()->toSql();

    expect($sql)->not->toContain('"password"');
    expect($sql)->not->toContain('"remember_token"');
    expect($sql)->toContain('"name"');
    expect($sql)->toContain('"email"');
});

test('an explicitly listed column is searched even if hidden', function () {
    User::forceCreate([
        'name' => 'Alice',
        'email' => 'a@example.test',
        'password' => 'irrelevant',
        'remember_token' => 'SECRETTOKEN',
    ]);

    // Naming a column explicitly is a deliberate act; only the `['*']`
    // expansion is filtered.
    $request = makeRequest('http://localhost?search=SECRET');
    $builder = new IndexQueryBuilder(User::query(), $request);
    $builder->searchable(['remember_token'])->searchFromRequest($request);

    expect($builder->query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Opt-in strict searching
|--------------------------------------------------------------------------
*/

test('it can deny searching by default via defaultSearchable', function () {
    TestModel::factory(['name' => 'Zaphod'])->create();

    makeRequest('http://localhost?search=Zaphod');

    IndexQueryBuilder::defaultSearchable([]);

    try {
        expect(fn () => TestModel::index()->get())
            ->toThrow(InvalidArgumentException::class, 'Searching is not allowed on this index.');
    } finally {
        IndexQueryBuilder::defaultSearchable(['*']);
    }
});

test('an explicit searchable call still overrides a strict default', function () {
    TestModel::factory(['name' => 'Zaphod'])->create();
    TestModel::factory(['name' => 'nope'])->create();

    makeRequest('http://localhost?search=Zaphod');

    IndexQueryBuilder::defaultSearchable([]);

    try {
        expect(TestModel::index()->searchable(['name'])->get()->count())->toBe(1);
    } finally {
        IndexQueryBuilder::defaultSearchable(['*']);
    }
});

test('a strict default does not affect requests without a search term', function () {
    TestModel::factory()->count(3)->create();

    makeRequest('http://localhost');

    IndexQueryBuilder::defaultSearchable([]);

    try {
        expect(TestModel::index()->get()->count())->toBe(3);
    } finally {
        IndexQueryBuilder::defaultSearchable(['*']);
    }
});

test('it throws when searching an index with an empty searchable list', function () {
    TestModel::factory(['name' => 'Zaphod'])->create();

    makeRequest('http://localhost?search=Zaphod');

    expect(fn () => TestModel::index()->searchable([])->get())
        ->toThrow(InvalidArgumentException::class, 'Searching is not allowed on this index.');
});

test('a custom search callback alone is enough to allow searching', function () {
    TestModel::factory(['name' => 'nope', 'title' => 'Zaphod'])->create();
    TestModel::factory(['name' => 'nope', 'title' => 'nope'])->create();

    makeRequest('http://localhost?search=Zaphod');

    $index = TestModel::index()
        ->searchable([])
        ->search('by_title', fn ($query, $term) => $query->orWhere('title', 'like', "%{$term}%"))
        ->get();

    expect($index->count())->toBe(1);
});
