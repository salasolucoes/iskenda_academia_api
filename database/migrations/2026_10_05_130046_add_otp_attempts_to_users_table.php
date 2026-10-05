<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contador de tentativas falhadas de verificação de OTP. Ao atingir
     * MAX_OTP_ATTEMPTS o código é invalidado, obrigando a reenvio.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('otp_attempts');
        });
    }
};
