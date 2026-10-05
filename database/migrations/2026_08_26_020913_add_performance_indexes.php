<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // HIGH: courses.instructor_id — queried in 7+ locations
        Schema::table('courses', function (Blueprint $table) {
            $table->index('instructor_id');
        });

        // HIGH: enrollments.course_id — queried viawhereIn in dashboard/instructor stats
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('course_id');
        });

        // HIGH: tickets.assigned_to — admin ticket assignment filtering
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('assigned_to');
        });

        // HIGH: tickets.status standalone — admin dashboard
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status');
        });

        // HIGH: audit_logs.event_type — audit browsing
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('event_type');
        });

        // HIGH: order_items.course_id — revenue per instructor
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('course_id');
        });

        // MEDIUM: payment_vouchers.status standalone — admin dashboard
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->index('status');
        });

        // MEDIUM: orders.status standalone — admin dashboard
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
        });

        // MEDIUM: live_sessions.scheduled_start — scheduled queries
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->index('scheduled_start');
        });

        // MEDIUM: courses.category_id — course filtering by category
        Schema::table('courses', function (Blueprint $table) {
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['instructor_id']);
            $table->dropIndex(['category_id']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['status']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
        });

        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropIndex(['scheduled_start']);
        });
    }
};
