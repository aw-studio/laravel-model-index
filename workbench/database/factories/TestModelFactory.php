<?php

namespace Workbench\Database\Factories;

use Workbench\App\Models\User;
use Workbench\App\Models\TestModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @template TModel of \Workbench\App\Models\TestModel
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class TestModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = TestModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'age' => fake()->numberBetween(1, 100),
            //
        ];
    }
}
