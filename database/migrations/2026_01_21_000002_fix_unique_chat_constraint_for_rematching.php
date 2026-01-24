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

        // Drop the normalization triggers
        DB::unprepared('DROP TRIGGER IF EXISTS normalize_chat_pair');
        DB::unprepared('DROP TRIGGER IF EXISTS normalize_chat_pair_update');

        // Add a partial unique constraint that only applies to active chats
        // This allows users to be matched again after ending a chat
        DB::statement("
            CREATE UNIQUE INDEX unique_active_chat_pair 
            ON chats (guest_id_1, guest_id_2) 
            WHERE status = 'active' AND ended_at IS NULL
        ");

        // Add a trigger to prevent self-matching
        DB::unprepared('
            CREATE TRIGGER prevent_self_matching
            BEFORE INSERT ON chats
            FOR EACH ROW
            BEGIN
                IF NEW.guest_id_1 = NEW.guest_id_2 THEN
                    SIGNAL SQLSTATE \"45000\" 
                    SET MESSAGE_TEXT = \"Cannot create chat with same guest\";
                END IF;
            END;
        ');
    }

    public function down(): void
    {
        // Drop the partial unique constraint
        DB::statement('DROP INDEX IF EXISTS unique_active_chat_pair ON chats');

        // Drop the self-matching trigger
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_self_matching');

        // Restore the original unique constraint
        Schema::table('chats', function (Blueprint $table) {
            $table->unique(['guest_id_1', 'guest_id_2'], 'unique_chat_pair');
        });

        // Restore the normalization triggers
        DB::unprepared('
            CREATE TRIGGER normalize_chat_pair
            BEFORE INSERT ON chats
            FOR EACH ROW
            BEGIN
                DECLARE temp_id VARCHAR(36);
                
                IF NEW.guest_id_1 > NEW.guest_id_2 THEN
                    SET temp_id = NEW.guest_id_1;
                    SET NEW.guest_id_1 = NEW.guest_id_2;
                    SET NEW.guest_id_2 = temp_id;
                END IF;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER normalize_chat_pair_update
            BEFORE UPDATE ON chats
            FOR EACH ROW
            BEGIN
                DECLARE temp_id VARCHAR(36);
                
                IF NEW.guest_id_1 > NEW.guest_id_2 THEN
                    SET temp_id = NEW.guest_id_1;
                    SET NEW.guest_id_1 = NEW.guest_id_2;
                    SET NEW.guest_id_2 = temp_id;
                END IF;
            END;
        ');
    }
};
