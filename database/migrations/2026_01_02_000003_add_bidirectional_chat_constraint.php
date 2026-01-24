<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing unique constraint if it exists
        Schema::table('chats', function (Blueprint $table) {
            $table->dropUnique('unique_chat_pair');
        });

        // Create trigger to normalize guest IDs before insert
        // This ensures (guest_id_1, guest_id_2) is always sorted alphabetically
        DB::unprepared('
            DROP TRIGGER IF EXISTS normalize_chat_pair;
            
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

        // Create trigger for updates as well
        DB::unprepared('
            DROP TRIGGER IF EXISTS normalize_chat_pair_update;
            
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

        // Add unique constraint back (now works bidirectionally)
        Schema::table('chats', function (Blueprint $table) {
            $table->unique(['guest_id_1', 'guest_id_2'], 'unique_chat_pair');
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropUnique('unique_chat_pair');
        });

        DB::unprepared('DROP TRIGGER IF EXISTS normalize_chat_pair');
        DB::unprepared('DROP TRIGGER IF EXISTS normalize_chat_pair_update');

        Schema::table('chats', function (Blueprint $table) {
            $table->unique(['guest_id_1', 'guest_id_2'], 'unique_chat_pair');
        });
    }
};
