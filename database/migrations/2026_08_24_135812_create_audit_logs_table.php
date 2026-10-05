<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100);
            $table->string('auditable_type', 80);
            $table->uuid('auditable_id')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->string('actor_role', 40)->nullable();
            $table->ipAddress('actor_ip')->nullable();
            $table->jsonb('payload');
            $table->jsonb('previous_state')->nullable();
            $table->jsonb('new_state')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
            $table->index(['actor_id', 'occurred_at'], 'idx_audit_actor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
