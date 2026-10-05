<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Persistence\Eloquent\Models\Category;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Lesson;
use Infrastructure\Persistence\Eloquent\Models\Module;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create();
});

test('can list published courses', function () {
    Course::factory()->published()->count(3)->create(['category_id' => $this->category->id]);

    $response = $this->getJson('/api/v1/courses');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('draft courses are not listed in public catalog', function () {
    Course::factory()->create(['category_id' => $this->category->id, 'status' => 'draft']);
    Course::factory()->published()->create(['category_id' => $this->category->id]);

    $response = $this->getJson('/api/v1/courses');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('can filter courses by modality', function () {
    Course::factory()->published()->online()->create(['category_id' => $this->category->id]);
    Course::factory()->published()->create([
        'category_id' => $this->category->id,
        'modality' => 'presential',
    ]);

    $response = $this->getJson('/api/v1/courses?modality=online');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('can search courses by title', function () {
    Course::factory()->published()->create([
        'category_id' => $this->category->id,
        'title' => 'Laravel para Iniciantes',
    ]);
    Course::factory()->published()->create([
        'category_id' => $this->category->id,
        'title' => 'Vue.js Avançado',
    ]);

    $response = $this->getJson('/api/v1/courses?search=Laravel');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('can view a single course by id', function () {
    $course = Course::factory()->published()->create([
        'category_id' => $this->category->id,
        'title' => 'Meu Curso Teste',
    ]);

    $response = $this->getJson("/api/v1/courses/{$course->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Meu Curso Teste');
});

test('course show includes curriculum modules and lessons', function () {
    $course = Course::factory()->published()->create(['category_id' => $this->category->id]);
    $module = Module::factory()->create(['course_id' => $course->id, 'order' => 1]);
    Lesson::factory()->count(2)->create(['module_id' => $module->id]);

    $response = $this->getJson("/api/v1/courses/{$course->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'modules' => [
                    ['id', 'title', 'lessons' => [['id', 'title', 'duration_minutes']]],
                ],
            ],
        ]);
});

test('public curriculum does not expose lesson content urls', function () {
    $course = Course::factory()->published()->create(['category_id' => $this->category->id]);
    $module = Module::factory()->create(['course_id' => $course->id, 'order' => 1]);
    Lesson::factory()->create([
        'module_id' => $module->id,
        'content_url' => 'https://videos.example.com/secret.mp4',
    ]);

    $response = $this->getJson("/api/v1/courses/{$course->id}");

    $response->assertStatus(200)
        ->assertJsonMissing(['content_url' => 'https://videos.example.com/secret.mp4']);
});

test('returns 404 for non-existent course id', function () {
    $response = $this->getJson('/api/v1/courses/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

test('returns 404 for draft course', function () {
    $course = Course::factory()->create([
        'category_id' => $this->category->id,
        'title' => 'Curso Rascunho',
        'status' => 'draft',
    ]);

    $response = $this->getJson("/api/v1/courses/{$course->id}");

    $response->assertStatus(404);
});
