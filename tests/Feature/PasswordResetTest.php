<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->user = User::factory()->create([
        'password' => Hash::make('OldPassword1!'),
    ]);
});

describe('POST /api/v1/auth/forgot-password', function () {

    test('sends OTP to existing user and stores it', function () {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $this->user->email,
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Se o email estiver registado, receberá um código de recuperação.']);

        $this->user->refresh();
        $this->assertNotNull($this->user->otp_code);
        $this->assertNotNull($this->user->otp_expires_at);
        $this->assertEquals(6, strlen($this->user->otp_code));
        $this->assertTrue($this->user->otp_expires_at->isFuture());
    });

    test('returns generic message for non-existent email', function () {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'naoexiste@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Se o email estiver registado, receberá um código de recuperação.']);
    });

    test('validates email is required', function () {
        $response = $this->postJson('/api/v1/auth/forgot-password', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });

    test('validates email format', function () {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'invalido',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });
});

describe('POST /api/v1/auth/verify-reset-otp', function () {

    test('verifies valid OTP and returns token', function () {
        $otp = '123456';
        $this->user->forceFill([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $this->user->email,
            'otp_code' => $otp,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'token']);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $this->user->email,
            'token' => $token,
        ]);

        $this->user->refresh();
        $this->assertNull($this->user->otp_code);
        $this->assertNull($this->user->otp_expires_at);
    });

    test('rejects invalid OTP code', function () {
        $this->user->forceFill([
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $this->user->email,
            'otp_code' => '999999',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('otp_code');
    });

    test('rejects expired OTP code', function () {
        $this->user->forceFill([
            'otp_code' => '123456',
            'otp_expires_at' => now()->subMinutes(1),
        ])->save();

        $response = $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $this->user->email,
            'otp_code' => '123456',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('otp_code');
    });

    test('rejects non-existent user', function () {
        $response = $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => 'naoexiste@example.com',
            'otp_code' => '123456',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });

    test('validates all required fields', function () {
        $response = $this->postJson('/api/v1/auth/verify-reset-otp', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'otp_code']);
    });

    test('replaces old token when requesting new OTP', function () {
        $otp = '123456';
        $this->user->forceFill([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $response1 = $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $this->user->email,
            'otp_code' => $otp,
        ]);

        $firstToken = $response1->json('token');

        $newOtp = '654321';
        User::where('id', $this->user->id)->update([
            'otp_code' => $newOtp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $this->user->email,
            'otp_code' => $newOtp,
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
            'token' => $firstToken,
        ]);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $this->user->email,
            'token' => $response->json('token'),
        ]);
    });
});

describe('POST /api/v1/auth/reset-password', function () {

    test('resets password with valid token', function () {
        $token = 'valid-token-123456';
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => $token,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'NovaSenha1!',
            'password_confirmation' => 'NovaSenha1!',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Palavra-passe redefinida com sucesso.']);

        $this->user->refresh();
        $this->assertTrue(Hash::check('NovaSenha1!', $this->user->password));
        $this->assertFalse(Hash::check('OldPassword1!', $this->user->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
            'token' => $token,
        ]);
    });

    test('rejects invalid token', function () {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $this->user->email,
            'token' => 'token-invalido',
            'password' => 'NovaSenha1!',
            'password_confirmation' => 'NovaSenha1!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    });

    test('rejects expired token', function () {
        $token = 'expired-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => $token,
            'created_at' => now()->subMinutes(20),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'NovaSenha1!',
            'password_confirmation' => 'NovaSenha1!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
            'token' => $token,
        ]);
    });

    test('validates all required fields', function () {
        $response = $this->postJson('/api/v1/auth/reset-password', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'token', 'password']);
    });

    test('validates password confirmation matches', function () {
        $token = 'valid-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => $token,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'NovaSenha1!',
            'password_confirmation' => 'Diferente!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    });

    test('validates password meets requirements', function () {
        $token = 'valid-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => $token,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'fraca',
            'password_confirmation' => 'fraca',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    });

    test('works for instructor role', function () {
        $instructor = User::factory()->instructor()->create([
            'password' => Hash::make('OldPass1!'),
        ]);

        $token = 'instructor-token';
        DB::table('password_reset_tokens')->insert([
            'email' => $instructor->email,
            'token' => $token,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $instructor->email,
            'token' => $token,
            'password' => 'NewInstructor1!',
            'password_confirmation' => 'NewInstructor1!',
        ]);

        $response->assertOk();

        $instructor->refresh();
        $this->assertTrue(Hash::check('NewInstructor1!', $instructor->password));
    });
});
