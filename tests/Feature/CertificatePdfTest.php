<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Certificate;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('minio');
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('issue returns pdf_url for new certificate', function () {
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
        ->assertJsonStructure([
            'data' => [
                'certificate_id',
                'verification_hash',
                'pdf_url',
            ],
        ]);

    $this->assertNotEmpty($response->json('data.pdf_url'));
});

test('issue returns pdf_url for existing certificate', function () {
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
        ->assertJsonStructure([
            'data' => [
                'certificate_id',
                'verification_hash',
                'pdf_url',
            ],
        ]);
});

test('pdf file is stored in minio', function () {
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

    $hash = $response->json('data.verification_hash');
    Storage::disk('minio')->assertExists("certificates/{$hash}.pdf");
});

test('pdf_url is stored in certificate model', function () {
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
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue");

    $cert = Certificate::where('enrollment_id', $enrollment->id)->first();
    $this->assertNotNull($cert->pdf_url);
    $this->assertStringContainsString('certificates/', $cert->pdf_url);
});
