<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('video');
            $table->string('content_url')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['module_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
