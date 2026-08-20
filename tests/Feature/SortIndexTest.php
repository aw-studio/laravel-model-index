<?php

use AwStudio\ModelIndex\IndexQueryBuilder;
use Workbench\App\Models\TestModel;
use Illuminate\Database\Eloquent\Factories\Sequence;

test('It sorts records in the index listing', function () {
    TestModel::factory()
        ->count(10)
        ->sequence(fn($sequence) => [
            'name' => 'Name ' . $sequence->index,
            'age' => 10 + $sequence->index
        ])
        ->create();

    makeRequest('http://localhost?sort=age');

    $index = TestModel::index()->sortable(['age', 'name'])->get();

    expect($index->first()->age)->toBe(10);
});

test('It sorts records in the index listing in descending order with colon syntax', function () {
    TestModel::factory()
        ->count(10)
        ->sequence(fn($sequence) => [
            'name' => 'Name ' . $sequence->index,
            'age' => 10 + $sequence->index
        ])
        ->create();

    makeRequest('http://localhost?sort=age:desc');

    $index = TestModel::index()->sortable(['age', 'name'])->get();

    expect($index->first()->age)->toBe(19);
});

test('It sorts records in the index listing in descending order with hypen syntax', function () {
    TestModel::factory()
        ->count(10)
        ->sequence(fn($sequence) => ['name' => 'Name ' . $sequence->index, 'age' => 10 + $sequence->index])
        ->create();

    makeRequest('http://localhost?sort=-age');

    $index = TestModel::index()->sortable(['age', 'name'])->get();

    expect($index->first()->age)->toBe(19);
});

test('it sorts by multiple fields', function () {
    TestModel::factory()
        ->count(10)
        ->sequence(function ($sequence) {
            return [
                'name' => 'Name ' . $sequence->index,
                'age' => 20 + ($sequence->index % 5),
                'created_at' => now()->subDays($sequence->index),
            ];
        })
        ->create();

    // we sort by age first, then by created_at
    // there are 2 records with age 24, one created 9 days ago, the other 4 days ago
    // the record with age 24 and created_at 9 days ago should be first
    makeRequest('http://localhost?sort=-age,created_at');

    $firstItem = TestModel::index()->sortable(['age', 'created_at'])->get()->first();

    expect($firstItem->age)->toBe(24);
    expect($firstItem->created_at->toDateString())->toBe(now()->subDays(9)->toDateString());
});

/*
|--------------------------------------------------------------------------
| Opt-in strict sorting
|--------------------------------------------------------------------------
*/

test('it denies sorting by default', function () {
    TestModel::factory()->count(3)->create();

    makeRequest('http://localhost?sort=age');

    expect(fn () => TestModel::index()->get())
        ->toThrow(InvalidArgumentException::class, 'Sorting by age is not allowed.');
});

test('it can allow sorting by any column via config', function () {
    TestModel::factory()->count(3)->create();

    makeRequest('http://localhost?sort=age');

    config()->set('model-index.sortable', ['*']);

    expect(fn () => TestModel::index()->get())->not->toThrow(InvalidArgumentException::class);
});

test('an explicit sortable call still overrides a strict default', function () {
    TestModel::factory()
        ->count(3)
        ->sequence(fn ($sequence) => ['age' => 10 + $sequence->index])
        ->create();

    makeRequest('http://localhost?sort=age');

    config()->set('model-index.sortable', []);

    expect(TestModel::index()->sortable(['age'])->get()->first()->age)->toBe(10);
});

test('a strict default still rejects columns outside an explicit allowlist', function () {
    TestModel::factory()->count(3)->create();

    makeRequest('http://localhost?sort=name');

    config()->set('model-index.sortable', []);

    expect(fn () => TestModel::index()->sortable(['age'])->get())
        ->toThrow(InvalidArgumentException::class, 'Sorting by name is not allowed.');
});
