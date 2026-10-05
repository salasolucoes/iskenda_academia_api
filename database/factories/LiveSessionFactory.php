<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;

class LiveSessionFactory extends Factory
{
    protected $model = LiveSession::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'stream_key' => fake()->uuid(),
            'scheduled_start' => fake()->dateTimeBetween('+1 day', '+1 month'),
            'actual_start' => null,
            'actual_end' => null,
            'status' => 'scheduled',
        ];
    }

    public function live(): static
    {
        return $this->state([
            'status' => 'live',
            'actual_start' => now(),
        ]);
    }

    public function ended(): static
    {
        return $this->state([
            'status' => 'ended',
            'actual_start' => now()->subHour(),
            'actual_end' => now(),
        ]);
    }
}
