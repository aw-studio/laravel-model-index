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
