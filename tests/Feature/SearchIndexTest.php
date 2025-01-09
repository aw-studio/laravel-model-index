<?php

use Workbench\App\Models\TestModel;

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
