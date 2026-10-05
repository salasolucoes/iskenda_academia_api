<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_cents')->nullable()->after('file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropColumn('amount_cents');
        });
    }
};
