<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\User;

class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'course_id' => Course::factory(),
            'order_id' => null,
            'status' => 'active',
            'enrolled_at' => fake()->dateTimeThisYear(),
            'completed_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'completed_at' => fake()->dateTimeThisYear(),
        ]);
    }
}
