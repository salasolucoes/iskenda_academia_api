<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\Module;

class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(['video', 'pdf']),
            'content_url' => fake()->url(),
            'duration_minutes' => fake()->numberBetween(5, 120),
            'order' => fake()->numberBetween(1, 50),
        ];
    }

    public function video(): static
    {
        return $this->state(['type' => 'video']);
    }

    public function pdf(): static
    {
        return $this->state(['type' => 'pdf']);
    }

    public function live(): static
    {
        return $this->state(['type' => 'live', 'content_url' => null, 'duration_minutes' => null]);
    }
}
