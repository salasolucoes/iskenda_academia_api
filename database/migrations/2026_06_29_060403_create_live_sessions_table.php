<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lesson_id')->constrained('lessons');
            $table->string('stream_key')->unique();
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};
