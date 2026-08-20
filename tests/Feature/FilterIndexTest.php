<?php

use Illuminate\Support\Carbon;
use Workbench\App\Models\TestModel;
use Workbench\App\Models\User;

test('It filters with equal comperator by default', function () {
    TestModel::factory(['name' => 'foob'])->create();

    makeRequest('http://localhost?filter[name]=foob');

    $index = TestModel::index();
    expect($index->get()->count())->toBe(1);
});

test('it filters with alternative syntax', function () {
    TestModel::factory(['name' => 'foob'])->create();
    TestModel::factory(['name' => 'bar'])->create();

    makeRequest('http://localhost?filter[name]=foob,bar');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
});

test('it filters with Equal comperator', function () {
    TestModel::factory(['age' => 20])->create();
    $foo = TestModel::factory(['age' => 21])->create();

    makeRequest('http://localhost?filter[age][$eq]=21');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$foo->id]);
});

test('it filters with NotEqual comperator', function () {
    TestModel::factory(['age' => 20])->create();
    $foo = TestModel::factory(['age' => 21])->create();

    makeRequest('http://localhost?filter[age][$ne]=21');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([1]);
});

test('it filters with GreaterThan comperator', function () {
    TestModel::factory(['age' => 20])->create();
    $foo = TestModel::factory(['age' => 21])->create();

    makeRequest('http://localhost?filter[age][$gt]=20');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$foo->id]);
});

test('it filters with GreaterThanOrEqual comperator', function () {
    TestModel::factory(['age' => 18])->create();
    $foo = TestModel::factory(['age' => 20])->create();
    $bar = TestModel::factory(['age' => 21])->create();

    makeRequest('http://localhost?filter[age][$gte]=20');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([$foo->id, $bar->id]);
});

test('It filters with LessThan comperator', function () {
    TestModel::factory(['age' => 20])->create();
    $foo = TestModel::factory(['age' => 19])->create();

    makeRequest('http://localhost?filter[age][$lt]=20');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$foo->id]);
});

test('it filters with LessThanOrEqual comperator', function () {
    $baz = TestModel::factory(['age' => 18])->create();
    $foo = TestModel::factory(['age' => 20])->create();
    $bar = TestModel::factory(['age' => 21])->create();

    makeRequest('http://localhost?filter[age][$lte]=20');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([$baz->id, $foo->id]);
});

test('it filters with contains comperator', function () {
    TestModel::factory(['name' => 'foo'])->create();
    $bar = TestModel::factory(['name' => 'bar'])->create();

    makeRequest('http://localhost?filter[name][$contains]=ba');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$bar->id]);
});

test('it filters with notContains comperator', function () {
    TestModel::factory(['name' => 'foo'])->create();
    $bar = TestModel::factory(['name' => 'bar'])->create();

    makeRequest('http://localhost?filter[name][$notContains]=ba');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([1]);
});

test('it filters with between comperator', function () {
    TestModel::factory(['age' => 18])->create();
    $foo = TestModel::factory(['age' => 20])->create();
    $bar = TestModel::factory(['age' => 21])->create();

    makeRequest('http://localhost?filter[age][$between][0]=19&filter[age][$between][1]=21');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([$foo->id, $bar->id]);
});

test('it filters with null comperator', function () {
    TestModel::factory(['verified_at' => now()])->create();
    $foo = TestModel::factory(['verified_at' => null])->create();

    makeRequest('http://localhost?filter[verified_at][$null]');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$foo->id]);
});

test('it filters with not null comperator', function () {
    TestModel::factory(['verified_at' => now()])->create();
    $foo = TestModel::factory(['verified_at' => null])->create();

    makeRequest('http://localhost?filter[verified_at][$notNull]');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([1]);
});

test('if filters with startsWith comperator', function () {
    TestModel::factory(['name' => 'foobar'])->create();
    $bar = TestModel::factory(['name' => 'bar'])->create();

    makeRequest('http://localhost?filter[name][$startsWith]=ba');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$bar->id]);
});

test('it filters with endsWith comperator', function () {
    $foobar = TestModel::factory(['name' => 'foobar'])->create();
    $bar = TestModel::factory(['name' => 'bar'])->create();

    makeRequest('http://localhost?filter[name][$endsWith]=ar');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([$foobar->id, $bar->id]);
});

test('it filters filters multiple with nested OR and AND', function () {
    TestModel::factory([
        'name' => 'lol',
        'age' => 17,
    ])->count(5)->create();
    $bar = TestModel::factory(['name' => 'bar', 'age' => 18])->create();
    $foo = TestModel::factory(['name' => 'foo', 'age' => 20])->create();

    $filters = [
        'filter' => [
            '$or' => [
                [
                    'name' => [
                        '$eq' => 'foo',
                    ],
                ],
                [
                    'name' => [
                        '$eq' => 'bar',
                    ],
                ],
            ],
            'age' => [
                '$gt' => 17,
            ],
        ],
    ];

    $httpQuery = http_build_query($filters);

    makeRequest('http://localhost?'.$httpQuery);

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([$bar->id, $foo->id]);
});

test('it filters multiple in using nested array syntax', function () {
    TestModel::factory()->count(10)->create();

    makeRequest('http://localhost?filter[id][$in][0]=6&filter[id][$in][1]=8');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([6, 8]);
});

test('it filters multiple not in using nested array syntax', function () {
    TestModel::factory()->count(4)->create();

    makeRequest('http://localhost?filter[id][$notIn][0]=1&filter[id][$notIn][1]=2');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([3, 4]);
});

test('it filters OR connected filters', function () {
    TestModel::factory()->count(4)->create();
    $foo = TestModel::factory(['name' => 'foob'])->create();

    makeRequest('http://localhost?filter[$or][0][id]=1&filter[$or][1][name]=foob');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([1, $foo->id]);
});

test('it filters AND connected filters', function () {
    TestModel::factory(['name' => 'foob'])->count(2)->create();
    $foo = TestModel::factory(['name' => 'foob'])->create();

    makeRequest('http://localhost?filter[$and][0][id]='.$foo->id.'&filter[$and][1][name]=foob');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$foo->id]);
});

test('It filters by custom filters', function () {
    TestModel::factory(['name' => 'foon'])->create();
    TestModel::factory(['name' => 'foob'])->create();

    makeRequest('http://localhost?filter[customFilterKey]=foob');

    $index = TestModel::index()
        ->filter('customFilterKey', fn ($query, $value) => $query->where('name', $value))
        ->get();

    expect($index->count())->toBe(1);
    expect($index->first()->name)->toBe('foob');
});

test('it filters with multiple combined filters and nested logical filters', function () {
    $foo = TestModel::factory(['title' => 'Gatore', 'name' => 'Johnny', 'color' => 'red', 'age' => 20])->create();
    $foo = TestModel::factory(['title' => 'Gato', 'name' => 'John', 'color' => 'red', 'age' => 20])->create();
    $bar = TestModel::factory(['title' => 'Gato', 'name' => 'Paul', 'color' => 'blue', 'age' => 21])->create();
    $baz = TestModel::factory(['title' => 'Gato', 'name' => 'George', 'color' => 'green', 'age' => 22])->create();
    $qux = TestModel::factory(['title' => 'Gato', 'name' => 'Ringo', 'color' => 'yellow', 'age' => 23])->create();

    Carbon::setTestNow(now()->subDays(2));
    $bar = TestModel::factory(['title' => 'Gato', 'name' => 'John', 'color' => 'blue', 'age' => 21])->create();
    Carbon::setTestNow();

    $complexFilter = [
        'created_at' => ['$between' => [now()->subDays(1)->toDateString(), now()->addDays(1)->toDateString()]],
        'title' => ['$eq' => 'Gato'],
        '$or' => [
            ['name' => ['$contains' => 'John']],
            ['name' => ['$contains' => 'Paul']],
            [
                '$and' => [
                    ['color' => ['$eq' => 'blue']],
                    ['size' => ['$eq' => 'S']],
                    ['name' => ['$contains' => 'Paul']],
                ],
            ],
        ],
        '$and' => [
            ['name' => ['$notContains' => 'George']],
            ['name' => ['$notContains' => 'Ringo']],
            ['age' => ['$gt' => 18]],
            ['color' => ['$in' => ['red', 'blue']]],
            [
                '$or' => [
                    ['name' => ['$contains' => 'John']],
                    ['name' => ['$contains' => 'Paul']],
                ],
            ],
        ],
    ];

    $httpQuery = http_build_query(['filter' => $complexFilter]);

    makeRequest('http://localhost?'.$httpQuery);

    // ray()->showQueries();
    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
});

test('It can handle relation filters with custom filters', function () {
    $model = TestModel::factory()
        ->for(\Workbench\Database\Factories\UserFactory::new(['name' => 'Max']))
        ->create();

    makeRequest('http://localhost?filter[user.name]=Max');
    $index = TestModel::index()
        ->filter('user.name', function ($query, $value) {
            $query->whereHas('user', function ($query) use ($value) {
                $query->where('name', $value);
            });
        })
        ->get();
    expect($index->first()->user->name)->toBe('Max');
});

test('it filters with multiple values using in operator', function () {
    $a = TestModel::factory(['name' => 'Alice'])->create();
    $b = TestModel::factory(['name' => 'Bob'])->create();
    TestModel::factory(['name' => 'Charlie'])->create();

    makeRequest('http://localhost?filter[name][]=Alice&filter[name][]=Bob');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('name')->toArray())->toBe(['Alice', 'Bob']);
});

test('it filters with multiple values using explicit $in operator', function () {
    $a = TestModel::factory(['id' => 1])->create();
    $b = TestModel::factory(['id' => 2])->create();
    $c = TestModel::factory(['id' => 3])->create();

    makeRequest('http://localhost?filter[id][$in][0]=1&filter[id][$in][1]=2');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('id')->toArray())->toBe([1, 2]);
});

test('it filters with multiple values using $notIn operator', function () {
    $a = TestModel::factory(['id' => 1])->create();
    $b = TestModel::factory(['id' => 2])->create();
    $c = TestModel::factory(['id' => 3])->create();

    makeRequest('http://localhost?filter[id][$notIn][0]=1&filter[id][$notIn][1]=2');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([3]);
});

test('it filters with custom callback using multiple values', function () {
    $a = TestModel::factory(['name' => 'Alice'])->create();
    $b = TestModel::factory(['name' => 'Bob'])->create();
    TestModel::factory(['name' => 'Charlie'])->create();

    makeRequest('http://localhost?filter[customNames][]=Alice&filter[customNames][]=Bob');

    $index = TestModel::index()
        ->filter('customNames', function ($query, $values) {
            $query->whereIn('name', $values);
        })
        ->get();

    expect($index->count())->toBe(2);
    expect($index->pluck('name')->toArray())->toBe(['Alice', 'Bob']);
});

test('it filters with nested OR containing multi-value arrays', function () {
    $a = TestModel::factory(['name' => 'Alice', 'age' => 20])->create();
    $b = TestModel::factory(['name' => 'Bob', 'age' => 21])->create();
    $c = TestModel::factory(['name' => 'Charlie', 'age' => 22])->create();

    $filters = [
        'filter' => [
            '$or' => [
                ['name' => ['Alice', 'Bob']], // numeric array → in
                ['age' => ['$eq' => 22]],
            ],
        ],
    ];

    makeRequest('http://localhost?'.http_build_query($filters));

    $index = TestModel::index()->get();

    expect($index->pluck('name')->toArray())->toBe(['Alice', 'Bob', 'Charlie']);
});

/*
|--------------------------------------------------------------------------
| Case-insensitive operators
|--------------------------------------------------------------------------
|
| Driver note: MySQL's default collation (utf8mb4_unicode_ci) already makes
| both `=` and `LIKE` case-insensitive, so these operators are indistinguishable
| from their case-sensitive counterparts there. SQLite — which this suite runs
| on — uses BINARY collation for `=` (case-sensitive) but folds ASCII for `LIKE`
| (case-insensitive). PostgreSQL is case-sensitive for both.
|
| So on SQLite the $eqi/$nei tests below prove the case-folding itself, while
| the $containsi/$notContainsi tests prove the operators are wired up and
| receive their `%` wildcards at all.
|
*/

test('it filters with case-insensitive Equal comperator', function () {
    $foo = TestModel::factory(['name' => 'Foobar'])->create();
    TestModel::factory(['name' => 'Baz'])->create();

    makeRequest('http://localhost?filter[name][$eqi]=foobar');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$foo->id]);
});

test('it keeps the plain Equal comperator case-sensitive', function () {
    TestModel::factory(['name' => 'Foobar'])->create();

    makeRequest('http://localhost?filter[name][$eq]=foobar');

    expect(TestModel::index()->get()->count())->toBe(0);
});

test('it filters with case-insensitive NotEqual comperator', function () {
    TestModel::factory(['name' => 'Foobar'])->create();
    $baz = TestModel::factory(['name' => 'Baz'])->create();

    makeRequest('http://localhost?filter[name][$nei]=FOOBAR');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$baz->id]);
});

test('it filters with case-insensitive Contains comperator', function () {
    $foo = TestModel::factory(['name' => 'Foobar'])->create();
    TestModel::factory(['name' => 'Baz'])->create();

    makeRequest('http://localhost?filter[name][$containsi]=OOB');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$foo->id]);
});

test('it filters with case-insensitive NotContains comperator', function () {
    TestModel::factory(['name' => 'Foobar'])->create();
    $baz = TestModel::factory(['name' => 'Baz'])->create();

    makeRequest('http://localhost?filter[name][$notContainsi]=OOB');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$baz->id]);
});

test('it applies case-insensitive operators inside an OR group', function () {
    $foo = TestModel::factory(['name' => 'Foobar', 'age' => 20])->create();
    $baz = TestModel::factory(['name' => 'Baz', 'age' => 99])->create();
    TestModel::factory(['name' => 'Qux', 'age' => 30])->create();

    $filters = [
        'filter' => [
            '$or' => [
                ['name' => ['$eqi' => 'FOOBAR']],
                ['age' => ['$eq' => 99]],
            ],
        ],
    ];

    makeRequest('http://localhost?'.http_build_query($filters));

    $index = TestModel::index()->get();

    expect($index->pluck('id')->toArray())->toBe([$foo->id, $baz->id]);
});

test('it wraps identifiers for case-insensitive operators', function () {
    makeRequest('http://localhost?filter[name][$containsi]=foo');

    $sql = TestModel::index()->applyRequestQuery()->query()->toSql();

    expect($sql)->toContain('lower("name")');
});

/*
|--------------------------------------------------------------------------
| Unknown operators
|--------------------------------------------------------------------------
*/

test('it throws for an unknown filter operator', function () {
    TestModel::factory(['name' => 'Foobar'])->create();

    makeRequest('http://localhost?filter[name][$containz]=oob');

    expect(fn () => TestModel::index()->get())
        ->toThrow(
            InvalidArgumentException::class,
            'Unsupported operator \'$containz\' for field \'name\''
        );
});

test('it throws for an unknown operator instead of silently returning no matches', function () {
    TestModel::factory(['name' => 'Foobar'])->create();

    // Before this was an exception, the unknown operator was coerced to `=`
    // and the unwrapped value produced an empty result set, which reads as
    // "no matches" rather than "you used an operator that does not exist".
    makeRequest('http://localhost?filter[name][$notAnOperator]=oob');

    expect(fn () => TestModel::index()->get())->toThrow(InvalidArgumentException::class);
});

test('it throws for an unknown operator nested inside an OR group', function () {
    $filters = [
        'filter' => [
            '$or' => [
                ['name' => ['$nope' => 'Alice']],
                ['age' => ['$eq' => 22]],
            ],
        ],
    ];

    makeRequest('http://localhost?'.http_build_query($filters));

    expect(fn () => TestModel::index()->get())
        ->toThrow(InvalidArgumentException::class, '$nope');
});

test('it supports every documented filter operator', function (string $field, string $operator, $value) {
    TestModel::factory(['name' => 'Foobar', 'age' => 42, 'color' => 'red'])->create();

    makeRequest('http://localhost?'.http_build_query([
        'filter' => [$field => [$operator => $value]],
    ]));

    // The assertion is that this does not throw — it guards against someone
    // "fixing" a typo in the match arms and quietly dropping an operator.
    expect(TestModel::index()->get())->not->toBeNull();
})->with([
    ['name', '$eq', 'Foobar'],
    ['name', '$eqi', 'foobar'],
    ['name', '$ne', 'Baz'],
    ['name', '$nei', 'baz'],
    ['age', '$lt', 100],
    ['age', '$lte', 42],
    ['age', '$gt', 1],
    ['age', '$gte', 42],
    ['name', '$in', ['Foobar', 'Baz']],
    ['name', '$notIn', ['Baz']],
    ['name', '$contains', 'oob'],
    ['name', '$notContains', 'zzz'],
    ['name', '$containsi', 'OOB'],
    ['name', '$notContainsi', 'ZZZ'],
    ['age', '$between', [1, 100]],
    ['name', '$startsWith', 'Foo'],
    ['name', '$endsWith', 'bar'],
    ['color', '$null', ''],
    ['color', '$notNull', ''],
]);

/*
|--------------------------------------------------------------------------
| $between value handling
|--------------------------------------------------------------------------
|
| Decision (resolves the "do we have to cast other types?" TODO): the bounds
| are NOT cast. Casting to int was the obvious fix for the numeric case but
| would corrupt date bounds, which are a first-class use of $between — see the
| date test below. Numeric strings are handled correctly by the driver as-is,
| so leaving the values alone is right for both.
|
*/

test('it filters with Between comperator from a comma separated string', function () {
    TestModel::factory(['age' => 20])->create();
    TestModel::factory(['age' => 40])->create();

    makeRequest('http://localhost?filter[age][$between]=18,30');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->first()->age)->toBe(20);
});

test('it trims whitespace around Between bounds', function () {
    TestModel::factory(['age' => 20])->create();
    TestModel::factory(['age' => 40])->create();

    makeRequest('http://localhost?'.http_build_query([
        'filter' => ['age' => ['$between' => '18, 30']],
    ]));

    expect(TestModel::index()->get()->count())->toBe(1);
});

test('it filters a date range with Between without corrupting the bounds', function () {
    TestModel::factory(['verified_at' => '2026-03-15 10:00:00'])->create();
    TestModel::factory(['verified_at' => '2020-01-01 10:00:00'])->create();

    makeRequest('http://localhost?'.http_build_query([
        'filter' => ['verified_at' => ['$between' => '2026-01-01,2026-12-31']],
    ]));

    expect(TestModel::index()->get()->count())->toBe(1);
});

test('it throws when Between does not get exactly two bounds', function () {
    makeRequest('http://localhost?filter[age][$between]=18,30,40');

    expect(fn () => TestModel::index()->get())
        ->toThrow(InvalidArgumentException::class, "The '\$between' operator for field 'age' expects exactly two values");
});

/*
|--------------------------------------------------------------------------
| Multiple operators on one field
|--------------------------------------------------------------------------
|
| `parseCondition()` used to `return` inside its loop, so only the first
| operator per field survived: `filter[age][$gte]=18&filter[age][$lte]=30` —
| the README's own range example — applied `>= 18` and silently discarded the
| upper bound, returning too many rows.
|
*/

test('it applies every operator given for a field', function () {
    TestModel::factory(['age' => 10])->create();
    $inRange = TestModel::factory(['age' => 25])->create();
    TestModel::factory(['age' => 50])->create();

    makeRequest('http://localhost?filter[age][$gte]=18&filter[age][$lte]=30');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$inRange->id]);
});

test('it applies more than two operators for a field', function () {
    TestModel::factory(['age' => 25])->create();
    $match = TestModel::factory(['age' => 27])->create();
    TestModel::factory(['age' => 40])->create();

    makeRequest('http://localhost?filter[age][$gte]=18&filter[age][$lte]=30&filter[age][$ne]=25');

    $index = TestModel::index()->get();

    expect($index->pluck('id')->toArray())->toBe([$match->id]);
});

test('it combines multi-operator fields with other fields', function () {
    TestModel::factory(['name' => 'John', 'age' => 25])->create();
    TestModel::factory(['name' => 'Paul', 'age' => 25])->create();
    TestModel::factory(['name' => 'John', 'age' => 60])->create();

    makeRequest('http://localhost?filter[name]=John&filter[age][$gte]=18&filter[age][$lte]=30');

    expect(TestModel::index()->get()->count())->toBe(1);
});

test('it still applies a single operator unchanged', function () {
    TestModel::factory(['age' => 10])->create();
    TestModel::factory(['age' => 50])->create();

    makeRequest('http://localhost?filter[age][$gte]=18');

    expect(TestModel::index()->get()->count())->toBe(1);
});

test('it generates one condition per operator', function () {
    makeRequest('http://localhost?filter[age][$gte]=18&filter[age][$lte]=30');

    $sql = TestModel::index()->applyRequestQuery()->query()->toSql();

    expect($sql)->toContain('"age" >= ?');
    expect($sql)->toContain('"age" <= ?');
});

test('it validates every operator, not just the first', function () {
    makeRequest('http://localhost?filter[age][$gte]=18&filter[age][$nope]=30');

    expect(fn () => TestModel::index()->get())
        ->toThrow(InvalidArgumentException::class, '$nope');
});

test('multiple operators inside an OR branch inherit the group boolean', function () {
    // Characterization of a deliberate choice, not an oversight. Conditions are
    // emitted individually and take the boolean of the group they sit in, so
    // two operators inside one `$or` branch are ORed rather than ANDed. Use
    // `$between`, or separate `$and` groups, when a range is needed inside an
    // `$or`.
    TestModel::factory(['age' => 5])->create();
    TestModel::factory(['age' => 25])->create();
    TestModel::factory(['age' => 90])->create();

    $filters = [
        'filter' => [
            '$or' => [
                ['age' => ['$gte' => 18, '$lte' => 30]],
            ],
        ],
    ];

    makeRequest('http://localhost?'.http_build_query($filters));

    // `age >= 18 or age <= 30` matches every row.
    expect(TestModel::index()->get()->count())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Relation filtering
|--------------------------------------------------------------------------
|
| `searchable(['user.name'])` has always worked via orWhereHas, but filtering
| the same field required a hand-written custom callback. Dot notation now
| works in both.
|
*/

test('it filters on a related model with dot notation', function () {
    $alice = User::forceCreate(['name' => 'Alice', 'email' => 'a@example.test', 'password' => 'x']);
    $bob = User::forceCreate(['name' => 'Bob', 'email' => 'b@example.test', 'password' => 'x']);

    $match = TestModel::factory(['name' => 'first'])->create();
    $match->forceFill(['user_id' => $alice->id])->save();

    $other = TestModel::factory(['name' => 'second'])->create();
    $other->forceFill(['user_id' => $bob->id])->save();

    makeRequest('http://localhost?filter[user.name]=Alice');

    $index = TestModel::index()->filterable(['user.name'])->get();

    expect($index->count())->toBe(1);
    expect($index->first()->id)->toBe($match->id);
});

test('it applies operators to relation filters', function () {
    $young = User::forceCreate(['name' => 'Young', 'email' => 'y@example.test', 'password' => 'x']);
    $old = User::forceCreate(['name' => 'Old', 'email' => 'o@example.test', 'password' => 'x']);

    $a = TestModel::factory()->create();
    $a->forceFill(['user_id' => $young->id])->save();
    $b = TestModel::factory()->create();
    $b->forceFill(['user_id' => $old->id])->save();

    makeRequest('http://localhost?filter[user.name][$containsi]=YOUN');

    $index = TestModel::index()->filterable(['user.name'])->get();

    expect($index->count())->toBe(1);
    expect($index->first()->id)->toBe($a->id);
});

test('relation filters respect the filterable allowlist', function () {
    makeRequest('http://localhost?filter[user.name]=Alice');

    expect(fn () => TestModel::index()->filterable(['name'])->get())
        ->toThrow(InvalidArgumentException::class, 'Filtering by user.name is not allowed.');
});

test('a custom filter callback still wins over relation resolution', function () {
    $alice = User::forceCreate(['name' => 'Alice', 'email' => 'a@example.test', 'password' => 'x']);
    $match = TestModel::factory(['name' => 'wanted'])->create();
    $match->forceFill(['user_id' => $alice->id])->save();
    TestModel::factory(['name' => 'other'])->create();

    makeRequest('http://localhost?filter[user.name]=Alice');

    // Registering a callback is an explicit override; it must not be bypassed
    // by the new built-in relation handling.
    $index = TestModel::index()
        ->filter('user.name', fn ($query, $value) => $query->where('name', 'wanted'))
        ->get();

    expect($index->count())->toBe(1);
    expect($index->first()->name)->toBe('wanted');
});

test('a dotted field that is not a relation is treated as a column reference', function () {
    // Someone joining manually and filtering on `table.column` must keep
    // working - only fields whose first segment is an actual Eloquent relation
    // are resolved via whereHas.
    TestModel::factory(['name' => 'Alpha'])->create();
    TestModel::factory(['name' => 'Beta'])->create();

    makeRequest('http://localhost?filter[test_models.name]=Alpha');

    $index = TestModel::index()->filterable(['test_models.name'])->get();

    expect($index->count())->toBe(1);
    expect($index->first()->name)->toBe('Alpha');
});

test('it filters through a nested relation', function () {
    $alice = User::forceCreate(['name' => 'Alice', 'email' => 'a@example.test', 'password' => 'x']);
    $match = TestModel::factory()->create();
    $match->forceFill(['user_id' => $alice->id])->save();
    TestModel::factory()->create();

    makeRequest('http://localhost?filter[user.name][$startsWith]=Ali');

    expect(TestModel::index()->filterable(['user.name'])->get()->count())->toBe(1);
});
