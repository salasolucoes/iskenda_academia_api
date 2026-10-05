<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});

// ─── Modules ───────────────────────────────────────────────────

test('instructor can create module', function () {
    $response = $this->postJson("/api/v1/instructor/courses/{$this->course->id}/modules", [
        'title' => 'Módulo 1',
        'description' => 'Descrição do módulo',
    ], $this->headers);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Módulo 1')
        ->assertJsonPath('data.order', 1);
});

test('instructor can list modules', function () {
    Module::factory()->count(2)->create(['course_id' => $this->course->id]);

    $response = $this->getJson("/api/v1/instructor/courses/{$this->course->id}/modules", $this->headers);

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('instructor can update module', function () {
    $module = Module::factory()->create(['course_id' => $this->course->id]);

    $response = $this->putJson("/api/v1/instructor/modules/{$module->id}", [
        'title' => 'Módulo Atualizado',
        'description' => 'Nova descrição',
    ], $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Módulo Atualizado');
});

test('instructor can reorder module', function () {
    $module = Module::factory()->create(['course_id' => $this->course->id, 'order' => 1]);

    $response = $this->putJson("/api/v1/instructor/modules/{$module->id}", [
        'title' => $module->title,
        'description' => $module->description,
        'order' => 5,
    ], $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('data.order', 5);
});

test('instructor cannot delete module with lessons', function () {
    $module = Module::factory()->create(['course_id' => $this->course->id]);
    Lesson::factory()->create([
        'module_id' => $module->id,
    ]);

    $response = $this->deleteJson("/api/v1/instructor/modules/{$module->id}", headers: $this->headers);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['module']);
});

test('instructor can delete empty module', function () {
    $module = Module::factory()->create(['course_id' => $this->course->id]);

    $response = $this->deleteJson("/api/v1/instructor/modules/{$module->id}", headers: $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Módulo removido com sucesso.');
});

// ─── Lessons ───────────────────────────────────────────────────

test('instructor can create lesson', function () {
    $module = Module::factory()->create(['course_id' => $this->course->id]);

    $response = $this->postJson("/api/v1/instructor/modules/{$module->id}/lessons", [
        'title' => 'Aula 1',
        'description' => 'Descrição da aula',
        'type' => 'video',
        'content_url' => 'https://example.com/video.mp4',
        'duration_minutes' => 30,
    ], $this->headers);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Aula 1')
        ->assertJsonPath('data.type', 'video')
        ->assertJsonPath('data.order', 1);
});

test('instructor can update lesson', function () {
    $module = Module::factory()->create(['course_id' => $this->course->id]);
    $lesson = Lesson::factory()->create([
        'module_id' => $module->id,
    ]);

    $response = $this->putJson("/api/v1/instructor/lessons/{$lesson->id}", [
        'title' => 'Aula Atualizada',
        'description' => 'Nova descrição',
        'type' => 'pdf',
        'content_url' => 'https://example.com/doc.pdf',
        'duration_minutes' => 15,
    ], $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Aula Atualizada')
        ->assertJsonPath('data.type', 'pdf');
});

test('instructor can delete lesson', function () {
    $module = Module::factory()->create(['course_id' => $this->course->id]);
    $lesson = Lesson::factory()->create([
        'module_id' => $module->id,
    ]);

    $response = $this->deleteJson("/api/v1/instructor/lessons/{$lesson->id}", headers: $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Aula removida com sucesso.');
});

// ─── Authorization ─────────────────────────────────────────────

test('instructor cannot manage modules from another instructor course', function () {
    $otherInstructor = User::factory()->instructor()->create();
    $otherCourse = Course::factory()->create([
        'instructor_id' => $otherInstructor->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->postJson("/api/v1/instructor/courses/{$otherCourse->id}/modules", [
        'title' => 'Hackeado',
    ], $this->headers);

    $response->assertStatus(404);
});

test('student cannot access module endpoints', function () {
    $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
    $token = $student->createToken('auth-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/instructor/courses/{$this->course->id}/modules", [
            'title' => 'Teste',
        ]);

    $response->assertStatus(403);
});

test('unauthenticated user cannot access module endpoints', function () {
    $response = $this->postJson("/api/v1/instructor/courses/{$this->course->id}/modules", [
        'title' => 'Teste',
    ]);

    $response->assertStatus(401);
});
