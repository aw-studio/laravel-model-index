<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Workbench\App\Models\TestModel;

test('it paginates the index listing when perPage is set', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=5');

    expect(TestModel::index()->paginate()->count())->toBe(5);
});
test('It paginates the index results if page is set', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?page=2');

    expect(TestModel::index()->paginate()->count())->toBe(10);
});

/*
|--------------------------------------------------------------------------
| Page size bounds
|--------------------------------------------------------------------------
*/

test('it leaves perPage unbounded by default', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=1000');

    expect(TestModel::index()->paginate()->perPage())->toBe(1000);
});

test('it clamps perPage to the configured maximum', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=1000');

    $index = TestModel::index()->maxPerPage(50)->paginate();

    expect($index->perPage())->toBe(50);
    expect($index->count())->toBe(20);
});

test('it leaves a perPage below the maximum untouched', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=5');

    expect(TestModel::index()->maxPerPage(50)->paginate()->perPage())->toBe(5);
});

test('it falls back to the default page size for a non-positive perPage', function ($perPage) {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage='.$perPage);

    expect(TestModel::index()->paginate()->perPage())->toBe(10);
})->with([['0'], ['-5'], ['abc']]);

/*
|--------------------------------------------------------------------------
| Deterministic return types
|--------------------------------------------------------------------------
|
| `get()` used to return a paginator when the request happened to carry `page`
| or `perPage`, and a plain collection otherwise — so the caller's query string
| decided the response envelope and every consumer had to handle both. Whether
| an endpoint paginates is now the endpoint's decision.
|
*/

test('get always returns a collection, even with pagination params', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?page=2&perPage=5');

    $result = TestModel::index()->get();

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->count())->toBe(20);
});

test('get still applies filters, sorting and search', function () {
    TestModel::factory(['name' => 'Alpha', 'age' => 30])->create();
    TestModel::factory(['name' => 'Beta', 'age' => 10])->create();

    makeRequest('http://localhost?filter[age][$gte]=18&sort=-age&perPage=1');

    $result = TestModel::index()->get();

    expect($result->count())->toBe(1);
    expect($result->first()->name)->toBe('Alpha');
});

test('paginate always returns a paginator, even without pagination params', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost');

    $result = TestModel::index()->paginate();

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->perPage())->toBe(10);
    expect($result->total())->toBe(20);
});

test('paginate takes the page size from the request', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=5');

    expect(TestModel::index()->paginate()->perPage())->toBe(5);
});

test('an explicit paginate argument wins over the request', function () {
    TestModel::factory()->count(20)->create();

    makeRequest('http://localhost?perPage=5');

    expect(TestModel::index()->paginate(7)->perPage())->toBe(7);
});

test('paginate honours the requested page', function () {
    TestModel::factory()
        ->count(20)
        ->sequence(fn ($sequence) => ['age' => $sequence->index])
        ->create();

    makeRequest('http://localhost?page=2&perPage=5');

    $result = TestModel::index()->paginate();

    expect($result->currentPage())->toBe(2);
    expect($result->first()->age)->toBe(5);
});

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

test('it publishes a usable default configuration', function () {
    expect(config('model-index.per_page'))->toBe(10);
    expect(config('model-index.max_per_page'))->toBeNull();
    expect(config('model-index.sortable'))->toBe(['*']);
    expect(config('model-index.searchable'))->toBe(['*']);
});

test('it takes the default page size from config', function () {
    TestModel::factory()->count(20)->create();

    config()->set('model-index.per_page', 3);

    makeRequest('http://localhost');

    expect(TestModel::index()->paginate()->perPage())->toBe(3);
});

test('it takes the page-size ceiling from config', function () {
    TestModel::factory()->count(20)->create();

    config()->set('model-index.max_per_page', 15);

    makeRequest('http://localhost?perPage=1000');

    expect(TestModel::index()->paginate()->perPage())->toBe(15);
});

test('an explicit maxPerPage call overrides the configured ceiling', function () {
    TestModel::factory()->count(20)->create();

    config()->set('model-index.max_per_page', 15);

    makeRequest('http://localhost?perPage=1000');

    expect(TestModel::index()->maxPerPage(5)->paginate()->perPage())->toBe(5);
});
