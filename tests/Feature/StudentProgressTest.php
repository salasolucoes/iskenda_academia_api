<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LessonProgress;
use Infrastructure\Persistence\Eloquent\Models\Module;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('progress update marks lesson as completed when watched >= 90%', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create(['module_id' => $module->id, 'duration_minutes' => 10]);

    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 570,
            'last_position_seconds' => 570,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.is_completed', true);

    $progress = LessonProgress::where('enrollment_id', $enrollment->id)
        ->where('lesson_id', $lesson->id)
        ->first();
    expect($progress->is_completed)->toBeTrue();
});

test('progress update does not mark lesson as incomplete when watched < 90%', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create(['module_id' => $module->id, 'duration_minutes' => 10]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 300,
            'last_position_seconds' => 300,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.is_completed', false);
});

test('enrollment auto-completes when all lessons done', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson1 = Lesson::factory()->video()->create(['module_id' => $module->id, 'duration_minutes' => 10]);
    $lesson2 = Lesson::factory()->video()->create(['module_id' => $module->id, 'duration_minutes' => 10]);

    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson1->id}/progress", [
            'watched_seconds' => 600,
            'last_position_seconds' => 600,
        ])->assertOk();

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson2->id}/progress", [
            'watched_seconds' => 600,
            'last_position_seconds' => 600,
        ])->assertOk();

    $enrollment->refresh();
    expect($enrollment->status)->toBe('completed');
    expect($enrollment->completed_at)->not->toBeNull();
});

test('enrollment does not auto-complete when not all lessons done', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson1 = Lesson::factory()->video()->create(['module_id' => $module->id, 'duration_minutes' => 10]);
    Lesson::factory()->video()->create(['module_id' => $module->id, 'duration_minutes' => 10]);

    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson1->id}/progress", [
            'watched_seconds' => 600,
            'last_position_seconds' => 600,
        ])->assertOk();

    $enrollment->refresh();
    expect($enrollment->status)->toBe('active');
});

test('unauthenticated user cannot update progress', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create(['module_id' => $module->id]);

    $response = $this->postJson("/api/v1/classroom/{$lesson->id}/progress", [
        'watched_seconds' => 600,
        'last_position_seconds' => 600,
    ]);

    $response->assertStatus(401);
});

test('student not enrolled cannot update progress', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create(['module_id' => $module->id]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 600,
            'last_position_seconds' => 600,
        ]);

    $response->assertStatus(403);
});

test('progress validation requires all fields', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create(['module_id' => $module->id]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['watched_seconds', 'last_position_seconds']);
});

test('client cannot complete a lesson by claiming zero duration', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create([
        'module_id' => $module->id,
        'duration_minutes' => 10,
    ]);

    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    // The exploit: client sends duration_seconds = 0 to satisfy the old
    // `<= 0` early return. The field no longer exists in validation, so the
    // authoritative duration_minutes (10 -> 600s) governs instead.
    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 0,
            'last_position_seconds' => 0,
            'duration_seconds' => 0,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.is_completed', false);

    $progress = LessonProgress::where('enrollment_id', $enrollment->id)
        ->where('lesson_id', $lesson->id)
        ->first();

    expect($progress->is_completed)->toBeFalse();

    $enrollment->refresh();
    expect($enrollment->status)->toBe('active');
});

test('client cannot inflate watched progress beyond the lesson duration', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create([
        'module_id' => $module->id,
        'duration_minutes' => 10,
    ]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    // Claims a 1-second lesson while the real lesson is 600 seconds.
    $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 1,
            'last_position_seconds' => 1,
            'duration_seconds' => 1,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.is_completed', false);
});

test('lesson without duration is not auto-completed and requires manual completion', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create([
        'module_id' => $module->id,
        'duration_minutes' => null,
    ]);

    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    // Even watching far beyond any plausible duration must not auto-complete:
    // absence of authoritative duration must never grant completion.
    $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 999999,
            'last_position_seconds' => 999999,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.is_completed', false);

    $enrollment->refresh();
    expect($enrollment->status)->toBe('active');
});

test('watched seconds below threshold keeps lesson incomplete', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create([
        'module_id' => $module->id,
        'duration_minutes' => 10,
    ]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    // 539s of a 600s lesson is 89.8% — below the 90% threshold.
    $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 539,
            'last_position_seconds' => 539,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.is_completed', false);
});

test('progress endpoint ignores client-supplied duration_seconds in response', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->video()->create([
        'module_id' => $module->id,
        'duration_minutes' => 10,
    ]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 600,
            'last_position_seconds' => 600,
            'duration_seconds' => 1,
        ]);

    $response->assertStatus(200);

    // A client claiming a 1-second duration must not see a completed lesson,
    // and the server must not echo the rejected field.
    $response->assertJsonPath('data.is_completed', true)
        ->assertJsonMissingPath('data.duration_seconds');
});
