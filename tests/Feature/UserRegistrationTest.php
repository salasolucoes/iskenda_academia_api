<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users table has expected columns and creates with uuid', function () {
    $user = User::factory()->create([
        'role' => 'student',
        'phone' => null,
    ]);

    expect($user->id)->toBeUuid()
        ->and($user->role)->toBe('student')
        ->and($user->is_active)->toBeTrue()
        ->and($user->phone)->toBeNull()
        ->and($user->otp_code)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('can create instructor and admin users', function () {
    $instructor = User::factory()->create(['role' => 'instructor']);
    $admin = User::factory()->create(['role' => 'admin']);

    expect($instructor->role)->toBe('instructor');
    expect($admin->role)->toBe('admin');
});
