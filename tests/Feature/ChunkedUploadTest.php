<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Infrastructure\Persistence\Eloquent\Models\Category;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\Module;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->instructor = User::factory()->instructor()->create();
    $this->token = $this->instructor->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];

    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'category_id' => $this->category->id,
    ]);

    $this->module = Module::factory()->create([
        'course_id' => $this->course->id,
    ]);

    $this->lesson = Lesson::factory()->create([
        'module_id' => $this->module->id,
        'type' => 'video',
    ]);
});

test('instructor can init chunked upload session', function () {
    $response = $this->postJson('/api/v1/instructor/upload/init', [
        'lesson_id' => $this->lesson->id,
        'file_name' => 'video.mp4',
        'file_size' => 12 * 1024 * 1024,
        'mime_type' => 'video/mp4',
    ], $this->headers);

    $response->assertStatus(201)
        ->assertJsonPath('data.total_chunks', 3)
        ->assertJsonPath('data.chunk_size', 5 * 1024 * 1024)
        ->assertJsonStructure(['data' => ['session_id', 'chunk_size', 'total_chunks']]);
});

test('instructor cannot init session for lesson they do not own', function () {
    $otherInstructor = User::factory()->instructor()->create();
    $otherToken = $otherInstructor->createToken('auth-token')->plainTextToken;

    $response = $this->postJson('/api/v1/instructor/upload/init', [
        'lesson_id' => $this->lesson->id,
        'file_name' => 'video.mp4',
        'file_size' => 12 * 1024 * 1024,
        'mime_type' => 'video/mp4',
    ], ['Authorization' => "Bearer {$otherToken}"]);

    $response->assertStatus(404);
});

test('instructor can upload chunks', function () {
    $initRes = $this->postJson('/api/v1/instructor/upload/init', [
        'lesson_id' => $this->lesson->id,
        'file_name' => 'video.mp4',
        'file_size' => 12 * 1024 * 1024,
        'mime_type' => 'video/mp4',
    ], $this->headers);

    $sessionId = $initRes->json('data.session_id');

    for ($i = 0; $i < 3; $i++) {
        $chunk = UploadedFile::fake()->create('chunk.bin', 5 * 1024, 'video/mp4');

        $response = $this->post('/api/v1/instructor/upload/chunk', [
            'session_id' => $sessionId,
            'chunk_index' => $i,
            'chunk' => $chunk,
        ], $this->headers);

        $response->assertOk()
            ->assertJsonPath('data.chunks_received', $i + 1)
            ->assertJsonPath('data.total_chunks', 3);
    }
});

test('instructor can get upload status', function () {
    $initRes = $this->postJson('/api/v1/instructor/upload/init', [
        'lesson_id' => $this->lesson->id,
        'file_name' => 'video.mp4',
        'file_size' => 12 * 1024 * 1024,
        'mime_type' => 'video/mp4',
    ], $this->headers);

    $sessionId = $initRes->json('data.session_id');

    $chunk = UploadedFile::fake()->create('chunk.bin', 5 * 1024, 'video/mp4');
    $this->post('/api/v1/instructor/upload/chunk', [
        'session_id' => $sessionId,
        'chunk_index' => 0,
        'chunk' => $chunk,
    ], $this->headers);

    $response = $this->getJson("/api/v1/instructor/upload/{$sessionId}/status", $this->headers);

    $response->assertOk()
        ->assertJsonPath('data.chunks_received', 1)
        ->assertJsonPath('data.progress', 33)
        ->assertJsonPath('data.total_chunks', 3);
});

test('instructor cannot complete upload with missing chunks', function () {
    $initRes = $this->postJson('/api/v1/instructor/upload/init', [
        'lesson_id' => $this->lesson->id,
        'file_name' => 'video.mp4',
        'file_size' => 12 * 1024 * 1024,
        'mime_type' => 'video/mp4',
    ], $this->headers);

    $sessionId = $initRes->json('data.session_id');

    $chunk = UploadedFile::fake()->create('chunk.bin', 5 * 1024, 'video/mp4');
    $this->post('/api/v1/instructor/upload/chunk', [
        'session_id' => $sessionId,
        'chunk_index' => 0,
        'chunk' => $chunk,
    ], $this->headers);

    $response = $this->postJson("/api/v1/instructor/upload/{$sessionId}/complete", [], $this->headers);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Upload incompleto.');
});

test('unauthenticated user cannot init upload session', function () {
    $response = $this->postJson('/api/v1/instructor/upload/init', [
        'lesson_id' => $this->lesson->id,
        'file_name' => 'video.mp4',
        'file_size' => 1000,
        'mime_type' => 'video/mp4',
    ]);

    $response->assertStatus(401);
});
