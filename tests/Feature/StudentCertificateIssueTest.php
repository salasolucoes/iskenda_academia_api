<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('student can issue certificate for completed enrollment', function () {
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'completed',
        'enrolled_at' => now(),
        'completed_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue");

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['certificate_id', 'verification_hash']]);
});

test('student cannot issue certificate for incomplete enrollment', function () {
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Course is not yet completed.');
});

test('student cannot issue certificate for other students enrollment', function () {
    $otherStudent = User::factory()->create();
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $otherStudent->id,
        'course_id' => $course->id,
        'status' => 'completed',
        'enrolled_at' => now(),
        'completed_at' => now(),
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue");

    $response->assertStatus(404);
});

test('issuing certificate twice returns existing certificate', function () {
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'completed',
        'enrolled_at' => now(),
        'completed_at' => now(),
    ]);

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue")
        ->assertStatus(201);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['certificate_id', 'verification_hash']]);
});
