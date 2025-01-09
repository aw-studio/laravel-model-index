<?php

use Illuminate\Support\Carbon;
use Workbench\App\Models\TestModel;

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
    $baz  = TestModel::factory(['age' => 18])->create();
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
        'age' => 17
    ])->count(5)->create();
    $bar = TestModel::factory(['name' => 'bar', 'age' => 18])->create();
    $foo = TestModel::factory(['name' => 'foo', 'age' => 20])->create();

    $filters = [
        'filter' => [
            '$or' => [
                [
                    'name' => [
                        '$eq' => 'foo'
                    ]
                ],
                [
                    'name' => [
                        '$eq' => 'bar'
                    ]
                ],
            ],
            'age' => [
                '$gt' => 17
            ]
        ],
    ];

    $httpQuery = http_build_query($filters);

    makeRequest('http://localhost?' . $httpQuery);

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

    makeRequest('http://localhost?filter[$and][0][id]=' . $foo->id . '&filter[$and][1][name]=foob');

    $index = TestModel::index()->get();

    expect($index->count())->toBe(1);
    expect($index->pluck('id')->toArray())->toBe([$foo->id]);
});

test('It filters by custom filters', function () {
    TestModel::factory(['name' => 'foob'])->create();

    makeRequest('http://localhost?filter[customFilterKey]=foob');

    $index = TestModel::index()
        ->filter('customFilterKey', function ($query, $value) {
            $query->where('name', $value);
        })
        ->get();

    expect($index->count())->toBe(1);
});


test('it filters with multiple combined filters and nested logical filters', function () {
    $foo = TestModel::factory(['title' => 'Gatore', 'name' =>  'Johnny', 'color' => 'red', 'age' => 20])->create();
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

    makeRequest('http://localhost?' . $httpQuery);

    ray()->showQueries();
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
