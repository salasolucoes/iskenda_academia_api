<?php

use App\Models\User;
use Application\UseCases\Course\CreateLiveLessonUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;
use Infrastructure\Persistence\Eloquent\Models\Module;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instructor = User::factory()->create(['role' => 'instructor', 'email_verified_at' => now()]);
    $this->token = $this->instructor->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];

    $this->course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $this->module = Module::factory()->create(['course_id' => $this->course->id]);
});

test('create live lesson creates both lesson and live session', function () {
    $scheduledAt = now()->addDays(7)->toIso8601String();
    $contentUrl = json_encode([
        'scheduled_at' => $scheduledAt,
        'external_link' => 'https://zoom.us/j/123456789',
        'duration_minutes' => 60,
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/instructor/modules/{$this->module->id}/lessons", [
            'title' => 'Aula ao Vivo de Laravel',
            'type' => 'live',
            'content_url' => $contentUrl,
            'duration_minutes' => 60,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'live')
        ->assertJsonPath('data.title', 'Aula ao Vivo de Laravel');

    $lesson = Lesson::where('module_id', $this->module->id)->first();
    $this->assertNotNull($lesson);
    $this->assertEquals('live', $lesson->type);

    $liveSession = LiveSession::where('lesson_id', $lesson->id)->first();
    $this->assertNotNull($liveSession);
    $this->assertEquals('scheduled', $liveSession->status);
    $this->assertEquals('https://zoom.us/j/123456789', $liveSession->raw_link);
    $this->assertNotNull($liveSession->stream_key);
    $this->assertEquals(32, strlen($liveSession->stream_key));
});

test('create live lesson fails without scheduled_at', function () {
    $contentUrl = json_encode([
        'external_link' => 'https://zoom.us/j/123456789',
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/instructor/modules/{$this->module->id}/lessons", [
            'title' => 'Aula ao Vivo',
            'type' => 'live',
            'content_url' => $contentUrl,
        ]);

    $response->assertStatus(422);
});

test('create live lesson fails with past scheduled_at', function () {
    $scheduledAt = now()->subDay()->toIso8601String();
    $contentUrl = json_encode([
        'scheduled_at' => $scheduledAt,
        'external_link' => 'https://zoom.us/j/123456789',
    ]);

    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/instructor/modules/{$this->module->id}/lessons", [
            'title' => 'Aula ao Vivo',
            'type' => 'live',
            'content_url' => $contentUrl,
        ]);

    $response->assertStatus(422);
});

test('create live lesson fails with invalid content_url', function () {
    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/instructor/modules/{$this->module->id}/lessons", [
            'title' => 'Aula ao Vivo',
            'type' => 'live',
            'content_url' => 'not-a-json',
        ]);

    $response->assertStatus(422);
});

test('create live lesson fails without content_url', function () {
    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/instructor/modules/{$this->module->id}/lessons", [
            'title' => 'Aula ao Vivo',
            'type' => 'live',
        ]);

    $response->assertStatus(422);
});

test('create video lesson does not create live session', function () {
    $response = $this->withHeaders($this->headers)
        ->postJson("/api/v1/instructor/modules/{$this->module->id}/lessons", [
            'title' => 'Aula de Vídeo',
            'type' => 'video',
            'content_url' => 'https://example.com/video.mp4',
            'duration_minutes' => 30,
        ]);

    $response->assertStatus(201);

    $lesson = Lesson::where('module_id', $this->module->id)->first();
    $this->assertEquals('video', $lesson->type);

    $liveSession = LiveSession::where('lesson_id', $lesson->id)->first();
    $this->assertNull($liveSession);
});

test('create live lesson generates unique stream keys', function () {
    $scheduledAt = now()->addDays(7)->toIso8601String();
    $contentUrl = json_encode([
        'scheduled_at' => $scheduledAt,
        'external_link' => 'https://zoom.us/j/123456789',
    ]);

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/instructor/modules/{$this->module->id}/lessons", [
            'title' => 'Live 1',
            'type' => 'live',
            'content_url' => $contentUrl,
        ]);

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/instructor/modules/{$this->module->id}/lessons", [
            'title' => 'Live 2',
            'type' => 'live',
            'content_url' => $contentUrl,
        ]);

    $sessions = LiveSession::all();
    $this->assertEquals(2, $sessions->count());
    $this->assertNotEquals($sessions[0]->stream_key, $sessions[1]->stream_key);
});

test('create live lesson via use case directly', function () {
    $scheduledAt = now()->addDays(7)->toIso8601String();
    $contentUrl = json_encode([
        'scheduled_at' => $scheduledAt,
        'external_link' => 'https://zoom.us/j/123456789',
    ]);

    $useCase = app(CreateLiveLessonUseCase::class);
    $lesson = $useCase->execute(
        moduleId: $this->module->id,
        title: 'Live via Use Case',
        description: 'Teste direto',
        contentUrl: $contentUrl,
        durationMinutes: 60,
    );

    $this->assertEquals('live', $lesson->type);
    $this->assertNotNull($lesson->liveSession);
    $this->assertEquals('scheduled', $lesson->liveSession->status);
    $this->assertEquals('https://zoom.us/j/123456789', $lesson->liveSession->raw_link);
});
