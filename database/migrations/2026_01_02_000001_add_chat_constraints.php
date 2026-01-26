<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate chats between same users
            // This ensures (guest_id_1, guest_id_2) and (guest_id_2, guest_id_1) are treated as same pair
            $table->unique(['guest_id_1', 'guest_id_2'], 'unique_chat_pair');
            
            // Add index for faster lookups by status
            $table->index(['status', 'ended_at'], 'idx_status_ended_at');
        });

        // Create a trigger to check for self-matching
        // Use different syntax for MySQL, PostgreSQL, and SQLite
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
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
        } elseif ($driver === 'pgsql') {
            DB::unprepared('
                CREATE OR REPLACE FUNCTION prevent_self_matching()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF NEW.guest_id_1 = NEW.guest_id_2 THEN
                        RAISE EXCEPTION \'Cannot create chat with same guest\';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER prevent_self_matching
                BEFORE INSERT ON chats
                FOR EACH ROW
                EXECUTE FUNCTION prevent_self_matching();
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
        Schema::table('chats', function (Blueprint $table) {
            $table->dropUnique('unique_chat_pair');
            $table->dropIndex('idx_status_ended_at');
        });

        DB::unprepared('DROP TRIGGER IF EXISTS prevent_self_matching');
    }
};
