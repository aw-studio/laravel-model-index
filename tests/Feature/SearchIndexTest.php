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
