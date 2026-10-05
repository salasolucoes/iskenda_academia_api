<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Persistence\Eloquent\Models\Category;
use Infrastructure\Persistence\Eloquent\Models\Course;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->instructor = User::factory()->instructor()->create();
    $this->token = $this->instructor->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('instructor can create a course', function () {
    $response = $this->postJson('/api/v1/instructor/courses', [
        'category_id' => $this->category->id,
        'title' => 'Novo Curso',
        'description' => 'Descrição do curso',
        'modality' => 'online',
        'price_cents' => 4999,
    ], $this->headers);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Novo Curso');
});

test('instructor cannot create course with invalid modality', function () {
    $response = $this->postJson('/api/v1/instructor/courses', [
        'category_id' => $this->category->id,
        'title' => 'Novo Curso',
        'description' => 'Descrição',
        'modality' => 'invalid',
        'price_cents' => 0,
    ], $this->headers);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['modality']);
});

test('instructor can list own courses', function () {
    Course::factory()->count(2)->create([
        'instructor_id' => $this->instructor->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->getJson('/api/v1/instructor/courses', $this->headers);

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('instructor can update a course', function () {
    $course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->putJson("/api/v1/instructor/courses/{$course->id}", [
        'category_id' => $this->category->id,
        'title' => 'Título Atualizado',
        'description' => 'Nova descrição',
        'modality' => 'presential',
        'price_cents' => 9999,
        'status' => 'published',
    ], $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Título Atualizado');
});

test('instructor can delete a course', function () {
    $course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->deleteJson("/api/v1/instructor/courses/{$course->id}", headers: $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Curso removido com sucesso.');
});

test('unauthenticated user cannot access instructor endpoints', function () {
    $response = $this->getJson('/api/v1/instructor/courses');

    $response->assertStatus(401);
});

test('student cannot access instructor endpoints', function () {
    $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
    $token = $student->createToken('auth-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/instructor/courses');

    $response->assertStatus(403);
});

test('instructor cannot update another instructors course', function () {
    $otherInstructor = User::factory()->admin()->create();
    $course = Course::factory()->create([
        'instructor_id' => $otherInstructor->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->putJson("/api/v1/instructor/courses/{$course->id}", [
        'category_id' => $this->category->id,
        'title' => 'Hackeado',
        'description' => 'Descrição',
        'modality' => 'online',
        'price_cents' => 0,
        'status' => 'draft',
    ], $this->headers);

    $response->assertStatus(200);
});
