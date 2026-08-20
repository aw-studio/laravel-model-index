<?php

use Workbench\App\Models\TestModel;

test('it paginates the index listing when perPage is set', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=5');

    expect(TestModel::index()->get()->count())->toBe(5);
});
test('It paginates the index results if page is set', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?page=2');

    expect(TestModel::index()->get()->count())->toBe(10);
});

/*
|--------------------------------------------------------------------------
| Page size bounds
|--------------------------------------------------------------------------
*/

test('it leaves perPage unbounded by default', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=1000');

    expect(TestModel::index()->get()->perPage())->toBe(1000);
});

test('it clamps perPage to the configured maximum', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=1000');

    $index = TestModel::index()->maxPerPage(50)->get();

    expect($index->perPage())->toBe(50);
    expect($index->count())->toBe(20);
});

test('it leaves a perPage below the maximum untouched', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=5');

    expect(TestModel::index()->maxPerPage(50)->get()->perPage())->toBe(5);
});

test('it falls back to the default page size for a non-positive perPage', function ($perPage) {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage='.$perPage);

    expect(TestModel::index()->get()->perPage())->toBe(10);
})->with([['0'], ['-5'], ['abc']]);
