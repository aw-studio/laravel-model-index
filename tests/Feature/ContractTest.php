<?php

use Workbench\App\Models\TestModel;

/*
|--------------------------------------------------------------------------
| Cross-package contract tests
|--------------------------------------------------------------------------
|
| This package and `@aw-studio/nuxt-laravel` are coupled ONLY by the query
| string — no codegen, no shared schema. A change on either side is invisible
| to the other until runtime, which is how the operator vocabularies drifted
| apart historically (the client advertised four operators this package never
| implemented, and they silently degraded to `=`).
|
| The literal query strings below are duplicated, deliberately, in the client's
| `test/contract.test.ts`, which asserts that its serializer PRODUCES exactly
| these strings. This file asserts that this package PARSES them into the
| intended query. If you change a literal here, change it there too — that
| pairing is the contract.
|
*/

beforeEach(function () {
    // Three rows chosen so every case below has a distinct expected result.
    TestModel::factory(['name' => 'John', 'age' => 20, 'title' => 'S', 'color' => 'red'])->create();
    TestModel::factory(['name' => 'Paul', 'age' => 40, 'title' => 'M', 'color' => null])->create();
    TestModel::factory(['name' => 'Ringo', 'age' => 60, 'title' => 'L', 'color' => 'blue'])->create();
});

test('the client query string parses into the intended query', function (string $query, array $expected) {
    makeRequest('http://localhost?'.$query);

    $names = TestModel::index()->get()->pluck('name')->sort()->values()->toArray();

    expect($names)->toBe($expected);
})->with([
    // --- literals mirrored from test/contract.test.ts -----------------------
    'a scalar filter' => [
        'filter[name]=John',
        ['John'],
    ],
    'an operator filter' => [
        'filter[age][$lte]=40',
        ['John', 'Paul'],
    ],
    'a case-insensitive operator' => [
        'filter[name][$containsi]=JOH',
        ['John'],
    ],
    'an $in list' => [
        'filter[title][$in][0]=S&filter[title][$in][1]=M',
        ['John', 'Paul'],
    ],
    'a $between tuple' => [
        'filter[age][$between][0]=18&filter[age][$between][1]=30',
        ['John'],
    ],
    'a null check' => [
        'filter[color][$null]=true',
        ['Paul'],
    ],
    'a not-null check' => [
        'filter[color][$notNull]=true',
        ['John', 'Ringo'],
    ],
    'an $or group' => [
        'filter[$or][0][name][$contains]=John&filter[$or][1][name][$contains]=Paul',
        ['John', 'Paul'],
    ],
    'a nested $and/$or group' => [
        'filter[$and][0][$or][0][name]=John&filter[$and][0][$or][1][name]=Paul&filter[$and][1][age][$gt]=30',
        ['Paul'],
    ],
    'a boolean value' => [
        'filter[title]=true',
        [],
    ],

    // --- remaining operators, same serialization shape ----------------------
    'equality, case-insensitive' => [
        'filter[name][$eqi]=john',
        ['John'],
    ],
    'inequality' => [
        'filter[name][$ne]=John',
        ['Paul', 'Ringo'],
    ],
    'inequality, case-insensitive' => [
        'filter[name][$nei]=JOHN',
        ['Paul', 'Ringo'],
    ],
    'greater than' => [
        'filter[age][$gt]=40',
        ['Ringo'],
    ],
    'greater than or equal' => [
        'filter[age][$gte]=40',
        ['Paul', 'Ringo'],
    ],
    'less than' => [
        'filter[age][$lt]=40',
        ['John'],
    ],
    'not in list' => [
        'filter[title][$notIn][0]=S',
        ['Paul', 'Ringo'],
    ],
    'contains' => [
        'filter[name][$contains]=oh',
        ['John'],
    ],
    'does not contain' => [
        'filter[name][$notContains]=oh',
        ['Paul', 'Ringo'],
    ],
    'does not contain, case-insensitive' => [
        'filter[name][$notContainsi]=OH',
        ['Paul', 'Ringo'],
    ],
    'starts with' => [
        'filter[name][$startsWith]=Jo',
        ['John'],
    ],
    'ends with' => [
        'filter[name][$endsWith]=go',
        ['Ringo'],
    ],
]);

test('the client sort serialization parses correctly', function (string $query, array $expected) {
    makeRequest('http://localhost?'.$query);

    $names = TestModel::index()->get()->pluck('name')->toArray();

    expect($names)->toBe($expected);
})->with([
    'ascending' => ['sort=age', ['John', 'Paul', 'Ringo']],
    'descending, hyphen' => ['sort=-age', ['Ringo', 'Paul', 'John']],
    'descending, colon' => ['sort=age:desc', ['Ringo', 'Paul', 'John']],
    'multi-column' => ['sort=title,-age', ['Ringo', 'Paul', 'John']],
]);

test('the full client request shape is accepted end to end', function () {
    // The exact string test/contract.test.ts asserts prepareQueryParams emits.
    makeRequest('http://localhost?page=1&perPage=25&sort=-created_at&search=joh&filter[name][$containsi]=joh');

    $result = TestModel::index()->get();

    expect($result->pluck('name')->toArray())->toBe(['John']);
    expect($result->perPage())->toBe(25);
    expect($result->currentPage())->toBe(1);
});

test('every operator the client advertises is accepted by the server', function (string $operator, $value) {
    makeRequest('http://localhost?'.http_build_query([
        'filter' => ['name' => [$operator => $value]],
    ]));

    // The assertion is that this does not throw. The client's
    // FilterOperatorOption union lists exactly these operators; the server now
    // throws on unknown ones, so any drift is a runtime error, not an empty list.
    expect(fn () => TestModel::index()->get())->not->toThrow(InvalidArgumentException::class);
})->with([
    ['$eq', 'John'],
    ['$eqi', 'john'],
    ['$ne', 'John'],
    ['$nei', 'john'],
    ['$lt', 'Z'],
    ['$lte', 'Z'],
    ['$gt', 'A'],
    ['$gte', 'A'],
    ['$in', ['John', 'Paul']],
    ['$notIn', ['John']],
    ['$contains', 'oh'],
    ['$notContains', 'oh'],
    ['$containsi', 'OH'],
    ['$notContainsi', 'OH'],
    ['$between', ['A', 'Z']],
    ['$startsWith', 'Jo'],
    ['$endsWith', 'go'],
    ['$null', 'true'],
    ['$notNull', 'true'],
]);
