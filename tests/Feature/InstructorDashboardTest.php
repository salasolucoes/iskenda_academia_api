<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;
use Infrastructure\Persistence\Eloquent\Models\Module;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instructor = User::factory()->instructor()->create(['email_verified_at' => now()]);
    $this->token = $this->instructor->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('instructor dashboard returns 200 with correct structure', function () {
    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/dashboard');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total_students',
                'active_courses',
                'scheduled_live_sessions_count',
                'scheduled_live_sessions',
                'recent_enrollments',
            ],
        ]);
});

test('instructor dashboard counts active courses', function () {
    Course::factory()->create(['instructor_id' => $this->instructor->id]);
    Course::factory()->create(['instructor_id' => $this->instructor->id]);
    Course::factory()->create(['instructor_id' => $this->instructor->id]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.active_courses', 3);
});

test('instructor dashboard counts distinct students', function () {
    $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $student1 = User::factory()->create(['email_verified_at' => now()]);
    $student2 = User::factory()->create(['email_verified_at' => now()]);

    Enrollment::factory()->active()->create([
        'student_id' => $student1->id,
        'course_id' => $course->id,
    ]);
    Enrollment::factory()->active()->create([
        'student_id' => $student2->id,
        'course_id' => $course->id,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_students', 2);
});

test('instructor dashboard returns scheduled live sessions', function () {
    $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->live()->create(['module_id' => $module->id]);
    LiveSession::factory()->create([
        'lesson_id' => $lesson->id,
        'status' => 'scheduled',
        'scheduled_start' => now()->addDays(3),
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.scheduled_live_sessions_count', 1)
        ->assertJsonCount(1, 'data.scheduled_live_sessions');
});

test('instructor dashboard returns recent enrollments', function () {
    $course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $student = User::factory()->create(['email_verified_at' => now()]);

    Enrollment::factory()->active()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/dashboard');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.recent_enrollments');
});

test('instructor dashboard returns zeros when no data', function () {
    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/instructor/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_students', 0)
        ->assertJsonPath('data.active_courses', 0)
        ->assertJsonPath('data.scheduled_live_sessions_count', 0)
        ->assertJsonCount(0, 'data.scheduled_live_sessions')
        ->assertJsonCount(0, 'data.recent_enrollments');
});

test('unauthenticated user cannot access instructor dashboard', function () {
    $response = $this->getJson('/api/v1/instructor/dashboard');

    $response->assertStatus(401);
});

test('student cannot access instructor dashboard', function () {
    $student = User::factory()->create(['email_verified_at' => now()]);
    $token = $student->createToken('auth-token')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/instructor/dashboard');

    $response->assertStatus(403);
});
