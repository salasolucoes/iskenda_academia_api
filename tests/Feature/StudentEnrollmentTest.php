<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment as EnrollmentModel;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LessonProgress;
use Infrastructure\Persistence\Eloquent\Models\Module;
use Infrastructure\Services\CertificatePdfService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
});

test('student can list enrollments', function () {
    $course = Course::factory()->create();
    EnrollmentModel::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/enrollments');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('student cannot access classroom of another student', function () {
    $otherStudent = User::factory()->create();
    $course = Course::factory()->create();
    $enrollment = EnrollmentModel::create([
        'id' => Str::uuid(),
        'student_id' => $otherStudent->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/classroom/{$enrollment->id}");

    $response->assertStatus(404);
});

test('classroom returns course with modules, lessons and progress', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $lessonDone = Lesson::factory()->create(['module_id' => $module->id, 'order' => 1]);
    Lesson::factory()->create(['module_id' => $module->id, 'order' => 2]);

    $enrollment = EnrollmentModel::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    LessonProgress::create([
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lessonDone->id,
        'watched_seconds' => 600,
        'last_position_seconds' => 600,
        'is_completed' => true,
        'last_activity_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/classroom/{$enrollment->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'enrollment' => ['id', 'course_id', 'status'],
                'course' => [
                    'title',
                    'modules' => [
                        ['id', 'title', 'lessons' => [['id', 'title', 'content_url']]],
                    ],
                ],
                'progress' => [['lesson_id', 'is_completed', 'last_position_seconds']],
            ],
        ])
        ->assertJsonPath('data.progress.0.lesson_id', $lessonDone->id)
        ->assertJsonPath('data.progress.0.is_completed', true);
});

test('unauthenticated user cannot access enrollments', function () {
    $response = $this->getJson('/api/v1/enrollments');
    $response->assertStatus(401);
});

test('completing all lessons auto-completes enrollment and enables certificate', function () {
    $this->mock(CertificatePdfService::class, function ($m) {
        $m->shouldReceive('generate')->once()->andReturn('/storage/test.pdf');
    });

    $course = Course::factory()->published()->create();
    $module = Module::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $lesson = Lesson::factory()->create(['module_id' => $module->id, 'order' => 1, 'duration_minutes' => 10]);

    $enrollment = EnrollmentModel::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    // Complete the single lesson via progress endpoint
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/classroom/{$lesson->id}/progress", [
            'watched_seconds' => 600,
            'last_position_seconds' => 600,
        ])
        ->assertOk();

    // Enrollment should now be completed
    $enrollment->refresh();
    $this->assertEquals('completed', $enrollment->status);
    $this->assertNotNull($enrollment->completed_at);

    // Certificate issuance should succeed
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/certificates/{$enrollment->id}/issue")
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['certificate_id', 'verification_hash']]);
});
