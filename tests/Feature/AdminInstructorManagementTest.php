<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->token = $this->admin->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('admin can list instructors', function () {
    User::factory()->instructor()->count(3)->create();

    $response = $this->getJson('/api/v1/admin/instructors', $this->headers);

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('admin can create an instructor and otp is sent', function () {
    $response = $this->postJson('/api/v1/admin/instructors', [
        'name' => 'João Instrutor',
        'email' => 'joao@instrutor.com',
        'phone' => '912345678',
    ], $this->headers);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'João Instrutor')
        ->assertJsonPath('data.email', 'joao@instrutor.com');

    $this->assertDatabaseHas('users', [
        'email' => 'joao@instrutor.com',
        'role' => 'instructor',
    ]);
});

test('admin cannot create instructor with duplicate email', function () {
    User::factory()->instructor()->create(['email' => 'joao@instrutor.com']);

    $response = $this->postJson('/api/v1/admin/instructors', [
        'name' => 'Outro',
        'email' => 'joao@instrutor.com',
    ], $this->headers);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('admin can view an instructor', function () {
    $instructor = User::factory()->instructor()->create();

    $response = $this->getJson("/api/v1/admin/instructors/{$instructor->id}", $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $instructor->id);
});

test('admin can update an instructor', function () {
    $instructor = User::factory()->instructor()->create();

    $response = $this->putJson("/api/v1/admin/instructors/{$instructor->id}", [
        'name' => 'Nome Actualizado',
        'email' => 'actualizado@instrutor.com',
        'phone' => '987654321',
    ], $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Nome Actualizado')
        ->assertJsonPath('data.email', 'actualizado@instrutor.com');
});

test('admin can delete an instructor', function () {
    $instructor = User::factory()->instructor()->create();

    $response = $this->deleteJson("/api/v1/admin/instructors/{$instructor->id}", headers: $this->headers);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Instrutor removido com sucesso.');

    $this->assertSoftDeleted($instructor);
});

test('non-admin cannot access instructor management', function () {
    $instructor = User::factory()->instructor()->create();
    $token = $instructor->createToken('auth-token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/instructors');

    $response->assertStatus(403);
});

test('unauthenticated cannot access instructor management', function () {
    $response = $this->getJson('/api/v1/admin/instructors');

    $response->assertStatus(401);
});

test('instructor can initiate setup and receive otp', function () {
    $instructor = User::factory()->instructor()->unverified()->create();

    $response = $this->postJson('/api/v1/auth/instructor/initiate', [
        'email' => $instructor->email,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Código OTP enviado para o seu email.');
});

test('instructor can complete setup with valid otp', function () {
    $instructor = User::factory()->instructor()->unverified()->create([
        'otp_code' => 'Kx9#mP2v',
        'otp_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/instructor/complete', [
        'email' => $instructor->email,
        'otp_code' => 'Kx9#mP2v',
        'password' => 'N0va-S3nh@!',
        'password_confirmation' => 'N0va-S3nh@!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'user']);

    $this->assertDatabaseHas('users', [
        'id' => $instructor->id,
        'email_verified_at' => now(),
        'otp_code' => null,
    ]);
});

test('instructor setup fails with wrong otp', function () {
    $instructor = User::factory()->instructor()->unverified()->create([
        'otp_code' => 'Kx9#mP2v',
        'otp_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/instructor/complete', [
        'email' => $instructor->email,
        'otp_code' => 'Wrong0@!Code',
        'password' => 'N0va-S3nh@!',
        'password_confirmation' => 'N0va-S3nh@!',
    ]);

    $response->assertStatus(422);
});

test('instructor setup fails with expired otp', function () {
    $instructor = User::factory()->instructor()->unverified()->create([
        'otp_code' => 'Kx9#mP2v',
        'otp_expires_at' => now()->subMinutes(5),
    ]);

    $response = $this->postJson('/api/v1/auth/instructor/complete', [
        'email' => $instructor->email,
        'otp_code' => 'Kx9#mP2v',
        'password' => 'N0va-S3nh@!',
        'password_confirmation' => 'N0va-S3nh@!',
    ]);

    $response->assertStatus(422);
});
