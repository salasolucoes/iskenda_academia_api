<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LessonProgress;

class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'watched_seconds' => fake()->numberBetween(0, 3600),
            'last_position_seconds' => fake()->numberBetween(0, 3600),
            'is_completed' => false,
            'last_activity_at' => fake()->dateTimeThisYear(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'is_completed' => true,
            'watched_seconds' => 3600,
            'last_position_seconds' => 3600,
        ]);
    }
}
