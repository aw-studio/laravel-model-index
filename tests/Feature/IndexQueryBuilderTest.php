<?php

use Workbench\App\Models\TestModel;
use AwStudio\ModelIndex\IndexQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

test('It returns an instance of the IndexQueryBuilder', function () {
    expect(TestModel::index())->toBeInstanceOf(IndexQueryBuilder::class);
});

test('It returns records of the model', function () {
    $model = TestModel::factory()->create();
    $index = TestModel::index()->get();
    expect($index->first()->id)->toBe($model->id);
});

test('it allows chaining other methods of the default eloquent query builder', function () {
    $model = TestModel::factory()->create();
    $index = TestModel::index()->where('id', $model->id)->get();
    expect($index->first()->id)->toBe($model->id);
});

test('it returns the count instead of records', function () {
    TestModel::factory()->count(3)->create();
    TestModel::factory(['name' => 'foo'])->count(3)->create();

    makeRequest('http://localhost?filter[name]=foo');
    $index = TestModel::index()->count();
    expect($index)->toBe(3);
});

test('It accepts the request object as an optional argument', function () {
    TestModel::factory()->count(3)->create();
    TestModel::factory(['name' => 'foo'])->count(3)->create();
    $request = Request::create('http://localhost?filter[name]=foo');
    $index = TestModel::index($request)->count();
    expect($index)->toBe(3);
});

test('it prevents filtering by fields that are not allowed', function () {
    makeRequest('http://localhost?filter[foo]=bar');
    TestModel::index()
        ->filterable(['name'])
        ->count();
})->throws(InvalidArgumentException::class);

test('it prevents filtering by fields that are not allowed when using model method', function () {
    makeRequest('http://localhost?filter[foo]=bar');
    $foo = new class extends TestModel {
        public function filterable()
        {
            return ['name'];
        }
    };

    $foo::index()->count();
})->throws(InvalidArgumentException::class);

test('it prevents filtering by default if no filterables have been set', function () {
    $testModel = new class extends Model {
        use \AwStudio\ModelIndex\Traits\HasIndexQuery;
    };
    makeRequest('http://localhost?filter[name]=foo');
    $testModel::index()->count();
})->throws(InvalidArgumentException::class);
