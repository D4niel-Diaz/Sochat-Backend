<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make 'idle' a valid guest status on every driver.
 *
 * The earlier add_idle_status migrations only ever worked on PostgreSQL, and on
 * PostgreSQL they added a second CHECK constraint next to Laravel's original
 * guests_status_check (which still rejects 'idle'). This migration replaces
 * whatever status constraints exist with a single correct one. It is idempotent.
 */
return new class extends Migration
{
    private const STATUSES = ['idle', 'waiting', 'active', 'banned'];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE guests DROP CONSTRAINT IF EXISTS guests_status_check');
            DB::statement('ALTER TABLE guests DROP CONSTRAINT IF EXISTS check_status');
            DB::statement("ALTER TABLE guests ADD CONSTRAINT guests_status_check CHECK (status::text IN ('idle', 'waiting', 'active', 'banned'))");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE guests MODIFY status ENUM('idle', 'waiting', 'active', 'banned') NOT NULL DEFAULT 'waiting'");
        } else {
            Schema::table('guests', function (Blueprint $table) {
                $table->enum('status', self::STATUSES)->default('waiting')->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: removing 'idle' would break existing rows.
    }
};
