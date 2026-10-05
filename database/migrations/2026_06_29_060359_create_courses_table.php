<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('instructor_id')->constrained('users');
            $table->foreignUuid('category_id')->nullable()->constrained('categories');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('modality', 20)->default('online');
            $table->integer('price_cents')->default(0);
            $table->string('status', 20)->default('draft');
            $table->string('thumbnail_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'modality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
