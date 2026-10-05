<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('users');
            $table->foreignUuid('transaction_id')->nullable()->constrained('wallet_transactions');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
    }
};
