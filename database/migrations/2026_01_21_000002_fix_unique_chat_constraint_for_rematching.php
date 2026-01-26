<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing unique constraint that prevents re-matching
        Schema::table('chats', function (Blueprint $table) {
            $table->dropUnique('unique_chat_pair');
        });

        // Drop the normalization triggers (MySQL only)
        // These triggers don't exist in SQLite, so skip them
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                DB::unprepared('DROP TRIGGER IF EXISTS normalize_chat_pair');
                DB::unprepared('DROP TRIGGER IF EXISTS normalize_chat_pair_update');
            } catch (\Exception $e) {
                // Ignore errors if triggers don't exist
            }
        }

        // Add a partial unique constraint that only applies to active chats
        // This allows users to be matched again after ending a chat
        // Note: MySQL doesn't support partial indexes, so we use a different approach
        if (DB::connection()->getDriverName() === 'mysql') {
            // For MySQL, we'll use a trigger-based approach or application-level validation
            // For now, we'll skip the partial index and rely on application logic
        } else {
            // For PostgreSQL and SQLite, we can use partial indexes
            DB::statement("
                CREATE UNIQUE INDEX unique_active_chat_pair
                ON chats (guest_id_1, guest_id_2)
                WHERE status = 'active' AND ended_at IS NULL
            ");
        }

        // Add a trigger to prevent self-matching
        // Drop existing trigger first to avoid conflicts
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_self_matching ON chats');

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('
                CREATE TRIGGER prevent_self_matching
                BEFORE INSERT ON chats
                FOR EACH ROW
                BEGIN
                    IF NEW.guest_id_1 = NEW.guest_id_2 THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Cannot create chat with same guest";
                    END IF;
                END;
            ');
        } else {
            // SQLite syntax
            DB::unprepared('
                CREATE TRIGGER prevent_self_matching
                BEFORE INSERT ON chats
                WHEN NEW.guest_id_1 = NEW.guest_id_2
                BEGIN
                    SELECT RAISE(ABORT, "Cannot create chat with same guest");
                END;
            ');
        }
    }

    public function down(): void
    {
        // Drop the partial unique constraint (only if it exists)
        if (DB::connection()->getDriverName() !== 'mysql') {
            DB::statement('DROP INDEX IF EXISTS unique_active_chat_pair ON chats');
        }

        // Drop the self-matching trigger
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_self_matching ON chats');

        // Restore the original unique constraint
        Schema::table('chats', function (Blueprint $table) {
            $table->unique(['guest_id_1', 'guest_id_2'], 'unique_chat_pair');
        });

        // Note: Normalization triggers are not restored in down() method
        // They are only needed for MySQL and will be recreated when needed
    }
};
