<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Persistence\Eloquent\Models\Certificate;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LessonProgress;
use Infrastructure\Persistence\Eloquent\Models\Module;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instructor = User::factory()->instructor()->create(['email_verified_at' => now()]);
    $this->token = $this->instructor->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('instructor can list students with progress', function () {
    $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson1 = Lesson::factory()->create(['module_id' => $module->id]);
    $lesson2 = Lesson::factory()->create(['module_id' => $module->id]);
    $student = User::factory()->create(['email_verified_at' => now()]);

    $enrollment = Enrollment::factory()->active()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    LessonProgress::factory()->create([
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lesson1->id,
        'is_completed' => true,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/students');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', $student->name)
        ->assertJsonPath('data.0.email', $student->email)
        ->assertJsonPath('data.0.course', $course->title)
        ->assertJsonPath('data.0.progress', 50)
        ->assertJsonPath('data.0.cert_emitted', false);
});

test('instructor can list students with certificate', function () {
    $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->create(['module_id' => $module->id]);
    $student = User::factory()->create(['email_verified_at' => now()]);

    $enrollment = Enrollment::factory()->active()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    LessonProgress::factory()->create([
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lesson->id,
        'is_completed' => true,
    ]);

    Certificate::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/students');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.progress', 100)
        ->assertJsonPath('data.0.cert_emitted', true);
});

test('instructor only sees own students', function () {
    $otherInstructor = User::factory()->instructor()->create(['email_verified_at' => now()]);
    $course = Course::factory()->create(['instructor_id' => $otherInstructor->id]);
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->create(['module_id' => $module->id]);
    $student = User::factory()->create(['email_verified_at' => now()]);

    $enrollment = Enrollment::factory()->active()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/students');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('instructor dashboard returns empty when no enrollments', function () {
    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/students');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('unauthenticated user cannot access instructor students', function () {
    $response = $this->getJson('/api/v1/instructor/students');

    $response->assertStatus(401);
});

test('student cannot access instructor students', function () {
    $student = User::factory()->create(['email_verified_at' => now()]);
    $token = $student->createToken('auth-token')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/instructor/students');

    $response->assertStatus(403);
});
