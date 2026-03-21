<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the MySQL triggers that were normalizing (sorting) guest_id_1/guest_id_2
 * on INSERT and UPDATE. This logic has been moved into ChatRepository::create()
 * using PHP's min()/max() — application-level sorting is deterministic,
 * testable, and doesn't rely on MySQL-specific features.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS normalize_chat_pair');
        DB::unprepared('DROP TRIGGER IF EXISTS normalize_chat_pair_update');
    }

    public function down(): void
    {
        // Triggers are not restored on rollback — the application-level sort
        // in ChatRepository::create() handles this correctly without triggers.
    }
};
