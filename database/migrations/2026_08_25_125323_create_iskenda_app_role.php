<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("CREATE ROLE iskenda_app LOGIN PASSWORD 'iskenda_secret'");
        DB::statement('GRANT CONNECT ON DATABASE iskenda TO iskenda_app');
        DB::statement('GRANT USAGE ON SCHEMA public TO iskenda_app');
        DB::statement('GRANT SELECT, INSERT ON ALL TABLES IN SCHEMA public TO iskenda_app');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT ON TABLES TO iskenda_app');

        DB::statement('REVOKE DELETE, UPDATE ON audit_logs FROM iskenda_app');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM iskenda_app');
        DB::statement('REVOKE ALL ON DATABASE iskenda FROM iskenda_app');
        DB::statement('DROP ROLE IF EXISTS iskenda_app');
    }
};
