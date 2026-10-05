<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('users');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users');
            $table->string('subject', 200);
            $table->text('description');
            $table->string('priority', 10)->default('low'); // low, medium, high
            $table->string('status', 20)->default('open'); // open, in_progress, resolved, closed
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
