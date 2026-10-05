<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments');
            $table->foreignUuid('lesson_id')->constrained('lessons');
            $table->integer('watched_seconds')->default(0);
            $table->integer('last_position_seconds')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamps();

            $table->unique(['enrollment_id', 'lesson_id']);
            $table->index(['enrollment_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
