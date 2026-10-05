<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;
use Infrastructure\Persistence\Eloquent\Models\Module;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('student can get join URL for live session', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->live()->create(['module_id' => $module->id]);
    $session = LiveSession::create([
        'id' => Str::uuid(),
        'lesson_id' => $lesson->id,
        'stream_key' => Str::random(32),
        'raw_link' => 'https://zoom.us/j/123456789',
        'status' => 'live',
    ]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$session->id}/join");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['join_url', 'expires_at']]);
});

test('student cannot join live session not enrolled in', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->live()->create(['module_id' => $module->id]);
    $session = LiveSession::create([
        'id' => Str::uuid(),
        'lesson_id' => $lesson->id,
        'stream_key' => Str::random(32),
        'raw_link' => 'https://zoom.us/j/123456789',
        'status' => 'live',
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$session->id}/join");

    $response->assertStatus(403)
        ->assertJsonPath('message', 'You are not enrolled in this course.');
});

test('student cannot join ended live session', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->live()->create(['module_id' => $module->id]);
    $session = LiveSession::create([
        'id' => Str::uuid(),
        'lesson_id' => $lesson->id,
        'stream_key' => Str::random(32),
        'raw_link' => 'https://zoom.us/j/123456789',
        'status' => 'ended',
    ]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$session->id}/join");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Live session is not available.');
});

test('student cannot join session with no raw_link', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->live()->create(['module_id' => $module->id]);
    $session = LiveSession::create([
        'id' => Str::uuid(),
        'lesson_id' => $lesson->id,
        'stream_key' => Str::random(32),
        'raw_link' => null,
        'status' => 'live',
    ]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$session->id}/join");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Transmission link not configured yet.');
});

test('unauthenticated user cannot join live session', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->live()->create(['module_id' => $module->id]);
    $session = LiveSession::create([
        'id' => Str::uuid(),
        'lesson_id' => $lesson->id,
        'stream_key' => Str::random(32),
        'raw_link' => 'https://zoom.us/j/123456789',
        'status' => 'live',
    ]);

    $response = $this->postJson("/api/v1/classroom/{$session->id}/join");

    $response->assertStatus(401);
});

test('two students get independent tokens for same session', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->live()->create(['module_id' => $module->id]);
    $session = LiveSession::create([
        'id' => Str::uuid(),
        'lesson_id' => $lesson->id,
        'stream_key' => Str::random(32),
        'raw_link' => 'https://zoom.us/j/123456789',
        'status' => 'live',
    ]);

    $studentA = User::factory()->create(['email_verified_at' => now()]);
    $tokenA = $studentA->createToken('auth-token')->plainTextToken;

    $studentB = User::factory()->create(['email_verified_at' => now()]);
    $tokenB = $studentB->createToken('auth-token')->plainTextToken;

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $studentA->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $studentB->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $responseA = $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
        ->postJson("/api/v1/classroom/{$session->id}/join");
    $responseA->assertStatus(200);

    $responseB = $this->withHeaders(['Authorization' => "Bearer {$tokenB}"])
        ->postJson("/api/v1/classroom/{$session->id}/join");
    $responseB->assertStatus(200);

    $joinUrlA = $responseA->json('data.join_url');
    $joinUrlB = $responseB->json('data.join_url');

    $this->assertNotSame($joinUrlA, $joinUrlB);
});

test('token is single use — second attempt fails', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->live()->create(['module_id' => $module->id]);
    $session = LiveSession::create([
        'id' => Str::uuid(),
        'lesson_id' => $lesson->id,
        'stream_key' => Str::random(32),
        'raw_link' => 'https://zoom.us/j/123456789',
        'status' => 'live',
    ]);

    Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/classroom/{$session->id}/join");

    $response->assertStatus(200);
    $joinUrl = $response->json('data.join_url');

    $parsed = parse_url($joinUrl);
    parse_str($parsed['query'] ?? '', $query);

    $this->actingAs($this->student);

    $firstAttempt = $this->get($joinUrl);
    $firstAttempt->assertStatus(302);

    $secondAttempt = $this->get($joinUrl);
    $secondAttempt->assertStatus(403);
});
