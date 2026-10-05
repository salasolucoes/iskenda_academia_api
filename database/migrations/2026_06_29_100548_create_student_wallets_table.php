<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->unique()->constrained('users');
            $table->integer('balance_cents')->default(0);
            $table->timestamps();

            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_wallets');
    }
};
