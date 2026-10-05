<?php

use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates an audit log entry via EloquentAuditLogger', function () {
    $logger = app(AuditLoggerInterface::class);

    $userId = DB::table('users')->insertGetId([
        'id' => (string) Str::uuid(),
        'name' => 'Audit User',
        'email' => 'audit_user_'.time().'@test.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $logger->log(
        'test.event',
        (object) ['id' => '00000000-0000-0000-0000-000000000001'],
        new ActorContext(actorId: $userId, actorRole: 'admin', actorIp: '127.0.0.1'),
        previousState: ['balance' => 100],
        newState: ['balance' => 200],
    );

    $log = DB::table('audit_logs')->orderByDesc('id')->first();

    expect($log->event_type)->toBe('test.event')
        ->and($log->auditable_type)->toBe('stdClass')
        ->and($log->auditable_id)->toBe('00000000-0000-0000-0000-000000000001')
        ->and((string) $log->actor_id)->toBe((string) $userId)
        ->and($log->actor_role)->toBe('admin')
        ->and($log->actor_ip)->toBe('127.0.0.1')
        ->and(json_decode($log->previous_state, true))->toBe(['balance' => 100])
        ->and(json_decode($log->new_state, true))->toBe(['balance' => 200]);
});

it('stores audit log with null actor context defaults', function () {
    $logger = app(AuditLoggerInterface::class);

    $logger->log(
        'auth.register',
        (object) ['id' => '00000000-0000-0000-0000-000000000099'],
        new ActorContext,
    );

    $log = DB::table('audit_logs')->orderByDesc('id')->first();

    expect($log->actor_id)->toBeNull()
        ->and($log->actor_role)->toBeNull()
        ->and($log->actor_ip)->toBeNull()
        ->and($log->previous_state)->toBeNull()
        ->and($log->new_state)->toBeNull();
});

it('registers auth.register audit event on user registration', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Audit Student',
        'email' => 'audit_student_'.time().'@test.com',
        'password' => 'Str0ng!Pass',
        'password_confirmation' => 'Str0ng!Pass',
    ]);

    $response->assertStatus(201);

    $log = DB::table('audit_logs')
        ->where('event_type', 'auth.register')
        ->orderByDesc('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_type)->toBe('User');
});

it('registers auth.login audit event on successful login', function () {
    $email = 'audit_login_'.time().'@test.com';
    $password = 'Str0ng!Pass';

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Login Auditee',
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $user = DB::table('users')->where('email', $email)->first();
    DB::table('users')->where('id', $user->id)->update(['email_verified_at' => now()]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);

    $response->assertOk();

    $log = DB::table('audit_logs')
        ->where('event_type', 'auth.login')
        ->orderByDesc('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id);
});

it('registers auth.login.failed audit event on bad credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@test.com',
        'password' => 'wrong',
    ]);

    $response->assertUnprocessable();

    $log = DB::table('audit_logs')
        ->where('event_type', 'auth.login.failed')
        ->orderByDesc('id')
        ->first();

    expect($log)->not->toBeNull();
});
