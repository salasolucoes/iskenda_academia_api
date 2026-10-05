<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infrastructure\Persistence\Eloquent\Models\Certificate;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\User;

class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'student_id' => User::factory(),
            'course_id' => Course::factory(),
            'issued_at' => fake()->dateTimeThisYear(),
            'verification_hash' => bin2hex(random_bytes(32)),
            'pdf_url' => null,
        ];
    }
}
