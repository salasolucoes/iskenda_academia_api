<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->uuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_hash', 64);
            $table->jsonb('response');
            $table->smallInteger('status_code');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at');

            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
