<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\PaymentVoucher;
use Infrastructure\Persistence\Eloquent\Models\StudentWallet;
use Infrastructure\Persistence\Eloquent\Models\WalletTransaction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
    $this->wallet = StudentWallet::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'balance_cents' => 0,
    ]);
});

test('student can view wallet with zero balance', function () {
    $this->wallet->update(['balance_cents' => 0]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/wallet');

    $response->assertStatus(200)
        ->assertJsonPath('balance_cents', 0);
});

test('wallet returns zero balance when not created', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/wallet');

    $response->assertStatus(200)
        ->assertJsonPath('balance_cents', 0);
});

test('student can upload voucher with amount', function () {
    Storage::fake('public');
    Notification::fake();

    $file = UploadedFile::fake()->image('comprovativo.jpg', 100, 100);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/wallet/voucher', [
            'file' => $file,
            'amount_cents' => 50000,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'voucher_id']);

    $voucher = PaymentVoucher::where('student_id', $this->student->id)->first();
    expect($voucher->amount_cents)->toBe(50000);
    expect($voucher->status)->toBe('pending');
});

test('voucher upload requires amount_cents', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('comprovativo.jpg', 100, 100);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/wallet/voucher', [
            'file' => $file,
        ]);

    $response->assertStatus(422);
});

test('voucher upload requires valid file type', function () {
    $file = UploadedFile::fake()->create('document.txt', 100);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/wallet/voucher', [
            'file' => $file,
            'amount_cents' => 10000,
        ]);

    $response->assertStatus(422);
});

test('admin can approve voucher and credit wallet', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $adminToken = $admin->createToken('admin-token')->plainTextToken;

    $voucher = PaymentVoucher::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'status' => 'pending',
        'file_path' => 'vouchers/test.jpg',
        'file_hash' => hash('sha256', 'test'),
        'amount_cents' => 50000,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/api/v1/admin/vouchers/{$voucher->id}/approve", [
            'amount_cents' => 50000,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Voucher aprovado e carteira creditada com sucesso.');

    $wallet = StudentWallet::where('student_id', $this->student->id)->first();
    expect($wallet->balance_cents)->toBe(50000);

    $transaction = WalletTransaction::where('wallet_id', $wallet->id)->first();
    expect($transaction->direction)->toBe('in');
    expect($transaction->type)->toBe('credit_purchase');

    $voucher->refresh();
    expect($voucher->status)->toBe('approved');
    expect($voucher->transaction_id)->toBe($transaction->id);
});

test('admin can reject voucher', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $adminToken = $admin->createToken('admin-token')->plainTextToken;

    $voucher = PaymentVoucher::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'status' => 'pending',
        'file_path' => 'vouchers/test.jpg',
        'file_hash' => hash('sha256', 'test'),
        'amount_cents' => 50000,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/api/v1/admin/vouchers/{$voucher->id}/reject", [
            'rejection_reason' => 'Comprovativo ilegível',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Voucher rejeitado.');

    $voucher->refresh();
    expect($voucher->status)->toBe('rejected');
    expect($voucher->rejection_reason)->toBe('Comprovativo ilegível');

    $wallet = StudentWallet::where('student_id', $this->student->id)->first();
    expect($wallet->balance_cents)->toBe(0);
});

test('admin can adjust student wallet balance', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $adminToken = $admin->createToken('admin-token')->plainTextToken;

    $this->wallet->update(['balance_cents' => 10000]);

    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/api/v1/admin/users/{$this->student->id}/wallet/adjust", [
            'amount_cents' => 5000,
            'description' => 'Correction for overpayment',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Saldo ajustado com sucesso.');

    $wallet = StudentWallet::where('student_id', $this->student->id)->first();
    expect($wallet->balance_cents)->toBe(15000);
});

test('admin can debit student wallet balance', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $adminToken = $admin->createToken('admin-token')->plainTextToken;

    $this->wallet->update(['balance_cents' => 10000]);

    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/api/v1/admin/users/{$this->student->id}/wallet/adjust", [
            'amount_cents' => -3000,
            'description' => 'Reversal of duplicate credit',
        ]);

    $response->assertStatus(200);

    $wallet = StudentWallet::where('student_id', $this->student->id)->first();
    expect($wallet->balance_cents)->toBe(7000);
});
