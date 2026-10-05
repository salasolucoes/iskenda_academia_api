<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registration rejects password without uppercase', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'senha@123',
        'password_confirmation' => 'senha@123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration rejects password without lowercase', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'SENHA@123',
        'password_confirmation' => 'SENHA@123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration rejects password without special character', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Senha123',
        'password_confirmation' => 'Senha123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration rejects password shorter than 8 chars', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'S3nh@',
        'password_confirmation' => 'S3nh@',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration accepts password with all requirements', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'S3nh@F0rte',
        'password_confirmation' => 'S3nh@F0rte',
    ]);

    $response->assertStatus(201);
});

test('instructor complete setup rejects weak password', function () {
    $instructor = User::factory()->instructor()->unverified()->create([
        'otp_code' => 'Kx9#mP2v',
        'otp_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/instructor/complete', [
        'email' => $instructor->email,
        'otp_code' => 'Kx9#mP2v',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('instructor set-password rejects weak password', function () {
    $instructor = User::factory()->instructor()->create([
        'otp_code' => 'Kx9#mP2v',
        'otp_expires_at' => now()->addMinutes(10),
    ]);

    $token = $instructor->createToken('auth-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/auth/instructor/set-password', [
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('admin create user rejects weak password', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('auth-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/users', [
            'name' => 'Novo Aluno',
            'email' => 'novo@test.com',
            'password' => 'fraca',
            'password_confirmation' => 'fraca',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('admin create user accepts strong password', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('auth-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/users', [
            'name' => 'Novo Aluno',
            'email' => 'novo@test.com',
            'password' => 'S3nh@F0rte',
            'password_confirmation' => 'S3nh@F0rte',
        ]);

    $response->assertStatus(201);
});
