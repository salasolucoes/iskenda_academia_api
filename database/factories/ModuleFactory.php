<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Module;

class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'order' => fake()->numberBetween(1, 20),
        ];
    }
}
