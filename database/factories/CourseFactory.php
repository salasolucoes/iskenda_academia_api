<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infrastructure\Persistence\Eloquent\Models\Category;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\User;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'instructor_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'description' => fake()->paragraphs(3, true),
            'modality' => fake()->randomElement(['online', 'presential', 'mixed']),
            'price_cents' => fake()->randomElement([0, 1999, 4999, 9999, 14999]),
            'status' => 'draft',
            'thumbnail_url' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published']);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }

    public function free(): static
    {
        return $this->state(['price_cents' => 0]);
    }

    public function online(): static
    {
        return $this->state(['modality' => 'online']);
    }
}
