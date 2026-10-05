<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('student can register successfully', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'S3nh@F0rte',
        'password_confirmation' => 'S3nh@F0rte',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'user_id']);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'role' => 'student',
    ]);

    $user = User::where('email', 'john@example.com')->first();
    expect($user->otp_code)->not->toBeNull();
    expect($user->email_verified_at)->toBeNull();
});

test('registration with duplicate email returns validation error', function () {
    User::factory()->create(['email' => 'john@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'S3nh@F0rte',
        'password_confirmation' => 'S3nh@F0rte',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration with weak password returns validation error', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => '123',
        'password_confirmation' => '123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration requires name email and password', function () {
    $response = $this->postJson('/api/v1/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});
