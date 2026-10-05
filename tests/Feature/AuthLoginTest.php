<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('verified student can login and receives token', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'john@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'user'])
        ->assertJsonPath('user.email', 'john@example.com');
});

test('login with wrong credentials returns error', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'john@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('unverified user login triggers otp and returns requires_otp', function () {
    User::factory()->unverified()->create([
        'email' => 'john@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'john@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('requires_otp', true);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_type' => (new User)->getMorphClass(),
    ]);
});

test('authenticated user can logout', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Sessão encerrada com sucesso.');
});

test('unauthenticated user cannot logout', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});
