<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\LiveSession;
use Infrastructure\Persistence\Eloquent\Models\Module;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['email_verified_at' => now()]);
    $this->token = $this->admin->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];

    $this->course = Course::create([
        'title' => 'Curso Teste',
        'slug' => 'curso-teste',
        'description' => 'Desc',
        'instructor_id' => $this->admin->id,
        'price_cents' => 50000,
        'is_published' => true,
    ]);

    $this->module = Module::create([
        'course_id' => $this->course->id,
        'title' => 'Módulo 1',
        'sort_order' => 1,
    ]);

    $this->lesson = Lesson::create([
        'module_id' => $this->module->id,
        'title' => 'Aula Ao Vivo',
        'type' => 'live',
        'sort_order' => 1,
        'content_url' => null,
    ]);

    $this->session = LiveSession::create([
        'lesson_id' => $this->lesson->id,
        'stream_key' => 'stream-key-123',
        'status' => 'scheduled',
        'scheduled_start' => now()->addHour(),
    ]);
});

test('admin can update live session link', function () {
    $response = $this->withHeaders($this->headers)
        ->patchJson("/api/v1/admin/live-sessions/{$this->session->id}/link", [
            'raw_link' => 'https://zoom.us/j/123456789',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.raw_link', 'https://zoom.us/j/123456789')
        ->assertJsonFragment(['message' => 'Link de transmissão atualizado com sucesso.']);

    $this->assertDatabaseHas('live_sessions', [
        'id' => $this->session->id,
        'raw_link' => 'https://zoom.us/j/123456789',
    ]);
});

test('admin can update link with invalid url returns 422', function () {
    $response = $this->withHeaders($this->headers)
        ->patchJson("/api/v1/admin/live-sessions/{$this->session->id}/link", [
            'raw_link' => 'not-a-url',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('raw_link');
});

test('admin can update link without raw_link returns 422', function () {
    $response = $this->withHeaders($this->headers)
        ->patchJson("/api/v1/admin/live-sessions/{$this->session->id}/link", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('raw_link');
});

test('admin can update link on non-existent session returns 404', function () {
    $response = $this->withHeaders($this->headers)
        ->patchJson('/api/v1/admin/live-sessions/00000000-0000-0000-0000-000000000000/link', [
            'raw_link' => 'https://zoom.us/j/123456789',
        ]);

    $response->assertStatus(404);
});

test('unauthenticated user cannot update live session link', function () {
    $response = $this->patchJson("/api/v1/admin/live-sessions/{$this->session->id}/link", [
        'raw_link' => 'https://zoom.us/j/123456789',
    ]);

    $response->assertStatus(401);
});

test('instructor cannot update live session link', function () {
    $instructor = User::factory()->instructor()->create(['email_verified_at' => now()]);
    $instructorToken = $instructor->createToken('auth-token')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$instructorToken}"])
        ->patchJson("/api/v1/admin/live-sessions/{$this->session->id}/link", [
            'raw_link' => 'https://zoom.us/j/123456789',
        ]);

    $response->assertStatus(403);
});

test('raw_link is included in live session resource', function () {
    $this->session->update(['raw_link' => 'https://meet.google.com/abc-defg-hij']);

    $response = $this->withHeaders($this->headers)
        ->getJson("/api/v1/admin/live-sessions/{$this->session->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.raw_link', 'https://meet.google.com/abc-defg-hij');
});

test('admin can update link on ended session', function () {
    $this->session->update(['status' => 'ended']);

    $response = $this->withHeaders($this->headers)
        ->patchJson("/api/v1/admin/live-sessions/{$this->session->id}/link", [
            'raw_link' => 'https://zoom.us/j/999999',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.raw_link', 'https://zoom.us/j/999999');
});
