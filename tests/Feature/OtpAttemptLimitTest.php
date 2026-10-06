<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create([
        'email' => 'otp_student@test.com',
        'otp_code' => '123456',
        'otp_expires_at' => now()->addMinutes(10),
        'otp_attempts' => 0,
        'email_verified_at' => null,
    ]);
});

describe('POST /api/v1/auth/verify-otp — limite de tentativas', function () {
    test('incrementa o contador a cada tentativa errada', function () {
        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $this->student->email,
            'otp_code' => '000000',
        ])->assertStatus(422);

        $this->student->refresh();
        expect($this->student->otp_attempts)->toBe(1)
            ->and($this->student->otp_code)->toBe('123456');
    });

    test('o contador persiste entre requests — não vive só em memória', function () {
        // Esta é a regressão que garante a Etapa 3: se o use case não fizer
        // save() no caminho de falha, o contador volta a zero a cada request
        // e o limite nunca acumularia.
        foreach (range(1, 3) as $ignored) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'email' => $this->student->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        $this->student->refresh();
        expect($this->student->otp_attempts)->toBe(3);
    });

    test('aceita o código correcto enquanto o contador estiver abaixo do limite', function () {
        foreach (range(1, 4) as $ignored) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'email' => $this->student->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        $this->student->refresh();
        expect($this->student->otp_attempts)->toBe(4);

        // 5.ª tentativa, mas com o código certo — tem de passar.
        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $this->student->email,
            'otp_code' => '123456',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);

        $this->student->refresh();
        expect($this->student->otp_attempts)->toBe(0)
            ->and($this->student->email_verified_at)->not->toBeNull();
    });

    test('invalida o código ao atingir MAX_OTP_ATTEMPTS', function () {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'email' => $this->student->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        $this->student->refresh();
        expect($this->student->otp_code)->toBeNull()
            ->and($this->student->otp_expires_at)->toBeNull()
            ->and($this->student->otp_attempts)->toBe(0);

        // O código correcto já não funciona — obriga a pedir reenvio.
        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $this->student->email,
            'otp_code' => '123456',
        ])->assertStatus(422);

        $this->student->refresh();
        expect($this->student->email_verified_at)->toBeNull();
    });

    test('a invalidação impede força bruta mesmo com pedidos ilimitados', function () {
        // Simula o atacante: 50 tentativas, das quais a 6.ª é a correcta.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'email' => $this->student->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        $this->student->refresh();
        expect($this->student->otp_code)->toBeNull();

        // Um novo ciclo de tentativas não consegue validar o código anterior.
        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $this->student->email,
            'otp_code' => '123456',
        ])->assertStatus(422);
    });

    test('regista auth.otp.failed na auditoria', function () {
        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $this->student->email,
            'otp_code' => '000000',
        ])->assertStatus(422);

        expect(DB::table('audit_logs')->where('event_type', 'auth.otp.failed')->exists())->toBeTrue();
    });

    test('regista auth.otp.verified quando o código é correcto', function () {
        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $this->student->email,
            'otp_code' => '123456',
        ])->assertOk();

        expect(DB::table('audit_logs')->where('event_type', 'auth.otp.verified')->exists())->toBeTrue();
    });

    test('código expirado não valida nem consome tentativa', function () {
        $this->student->forceFill([
            'otp_expires_at' => now()->subMinutes(1),
        ])->save();

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $this->student->email,
            'otp_code' => '000000',
        ])->assertStatus(422);

        $this->student->refresh();
        expect($this->student->otp_attempts)->toBe(0);
    });
});

describe('POST /api/v1/auth/verify-reset-otp — limite de tentativas', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'reset_student@test.com',
            'otp_code' => '654321',
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
        ]);
    });

    test('incrementa o contador a cada tentativa errada', function () {
        $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $this->user->email,
            'otp_code' => '000000',
        ])->assertStatus(422);

        $this->user->refresh();
        expect($this->user->otp_attempts)->toBe(1);
    });

    test('invalida o código ao atingir MAX_OTP_ATTEMPTS', function () {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/verify-reset-otp', [
                'email' => $this->user->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        $this->user->refresh();
        expect($this->user->otp_code)->toBeNull()
            ->and($this->user->otp_attempts)->toBe(0);
    });

    test('o código correcto deixa de funcionar após a invalidação', function () {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/verify-reset-otp', [
                'email' => $this->user->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $this->user->email,
            'otp_code' => '654321',
        ])->assertStatus(422);
    });

    test('aceita o código correcto enquanto o contador estiver abaixo do limite', function () {
        $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $this->user->email,
            'otp_code' => '654321',
        ])->assertOk()->assertJsonStructure(['message', 'token']);

        $this->user->refresh();
        expect($this->user->otp_attempts)->toBe(0);
    });

    test('forgot-password repõe o contador a zero ao reenviar', function () {
        $this->user->forceFill(['otp_attempts' => 3])->save();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $this->user->email,
        ])->assertOk();

        $this->user->refresh();
        expect($this->user->otp_attempts)->toBe(0)
            ->and($this->user->otp_code)->not->toBeNull();
    });
});

describe('POST /api/v1/auth/instructor/complete — limite de tentativas', function () {
    test('incrementa o contador e invalida a senha temporária ao limite', function () {
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'email' => 'instructor_brute@test.com',
            'otp_code' => 'Ab1@efgh',
            'otp_expires_at' => now()->addMinutes(60),
            'otp_attempts' => 0,
            'email_verified_at' => null,
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/instructor/complete', [
                'email' => $instructor->email,
                'otp_code' => 'Xy9#zzzz',
                'password' => 'N0va!SenhaForte',
                'password_confirmation' => 'N0va!SenhaForte',
            ])->assertStatus(422);
        }

        $instructor->refresh();
        expect($instructor->otp_code)->toBeNull()
            ->and($instructor->otp_attempts)->toBe(0);

        // A senha temporária correcta já não é aceite.
        $this->postJson('/api/v1/auth/instructor/complete', [
            'email' => $instructor->email,
            'otp_code' => 'Ab1@efgh',
            'password' => 'N0va!SenhaForte',
            'password_confirmation' => 'N0va!SenhaForte',
        ])->assertStatus(422);
    });

    test('aceita a senha temporária correcta enquanto o contador estiver abaixo do limite', function () {
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'email' => 'instructor_ok@test.com',
            'otp_code' => 'Ab1@efgh',
            'otp_expires_at' => now()->addMinutes(60),
            'otp_attempts' => 2,
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/instructor/complete', [
            'email' => $instructor->email,
            'otp_code' => 'Ab1@efgh',
            'password' => 'N0va!SenhaForte',
            'password_confirmation' => 'N0va!SenhaForte',
        ])->assertOk();

        $instructor->refresh();
        expect($instructor->email_verified_at)->not->toBeNull()
            ->and($instructor->otp_attempts)->toBe(0);
    });
});
