<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Certificate;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Services\CertificatePdfService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
});

test('public can verify a valid certificate', function () {
    $course = Course::factory()->create();
    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'completed',
        'enrolled_at' => now(),
        'completed_at' => now(),
    ]);

    $certificate = Certificate::create([
        'id' => Str::uuid(),
        'enrollment_id' => $enrollment->id,
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'issued_at' => now(),
        'verification_hash' => hash('sha256', 'test-hash'),
    ]);

    $response = $this->getJson("/api/v1/certificates/{$certificate->verification_hash}/verify");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['student_name', 'course_title', 'issued_at', 'valid']]);
});

test('public verify with invalid hash returns 404', function () {
    $response = $this->getJson('/api/v1/certificates/invalidhash123/verify');

    $response->assertStatus(404);
});

test('student can issue certificate for completed enrollment', function () {
    $this->mock(CertificatePdfService::class, function ($m) {
        $m->shouldReceive('generate')->once()->andReturn('/storage/test.pdf');
    });

    $course = Course::factory()->create();
    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'completed',
        'enrolled_at' => now(),
        'completed_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue");

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['certificate_id', 'verification_hash', 'pdf_url'],
        ]);
});

test('student cannot issue certificate for active enrollment', function () {
    $course = Course::factory()->create();
    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue");

    $response->assertStatus(422);
});

test('re-issuing certificate returns same id', function () {
    $this->mock(CertificatePdfService::class, function ($m) {
        $m->shouldReceive('generate')->andReturn('/storage/test.pdf');
    });

    $course = Course::factory()->create();
    $enrollment = Enrollment::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'completed',
        'enrolled_at' => now(),
        'completed_at' => now(),
    ]);

    $first = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue")
        ->json();

    $second = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue")
        ->json();

    $this->assertEquals($first['data']['certificate_id'], $second['data']['certificate_id']);
});
