<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('student_wallets');
            $table->integer('amount_cents');
            $table->string('direction', 10); // in, out
            $table->string('type', 30); // credit_purchase, course_payment, admin_adjustment, refund
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('status', 20)->default('completed'); // pending, completed, failed
            $table->string('reference_type', 50)->nullable();
            $table->string('reference_id', 36)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('wallet_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
