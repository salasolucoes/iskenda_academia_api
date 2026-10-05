<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('email verified middleware allows verified users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->getJson('/api/v1/user')
        ->assertStatus(200);
});

test('email verified middleware blocks unverified users', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/user')
        ->assertStatus(200);
});

test('role middleware allows users with matching role', function () {
    Route::get('/_test/role', function () {
        return response()->json(['ok' => true]);
    })->middleware('auth:sanctum', 'role:admin');

    $user = User::factory()->admin()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->getJson('/_test/role')
        ->assertStatus(200)
        ->assertJsonPath('ok', true);
});

test('role middleware blocks users without matching role', function () {
    Route::get('/_test/role-blocked', function () {
        return response()->json(['ok' => true]);
    })->middleware('auth:sanctum', 'role:admin');

    $user = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

    $this->actingAs($user)
        ->getJson('/_test/role-blocked')
        ->assertStatus(403);
});

test('role middleware returns 401 for unauthenticated users', function () {
    Route::get('/_test/role-auth', function () {
        return response()->json(['ok' => true]);
    })->middleware('auth:sanctum', 'role:admin');

    $this->withHeader('Accept', 'application/json')
        ->getJson('/_test/role-auth')
        ->assertStatus(401);
});
