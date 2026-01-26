<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL doesn't support altering enum types directly
        // Get the actual enum type name from the database
        $enumTypeName = DB::select("
            SELECT typname 
            FROM pg_type 
            WHERE typname LIKE '%guests%status%'
            AND typtype = 'e'
            LIMIT 1
        ");

        if ($enumTypeName) {
            $typeName = $enumTypeName[0]->typname;
            DB::statement("ALTER TYPE {$typeName} ADD VALUE 'idle' BEFORE 'banned'");
        } else {
            // Fallback: Recreate the column with the new enum
            DB::statement("ALTER TABLE guests ALTER COLUMN status TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE guests ADD CONSTRAINT check_status CHECK (status IN ('waiting', 'active', 'idle', 'banned'))");
        }
    }

    public function down(): void
    {
        // Update idle guests back to waiting before changing enum
        DB::statement("
            UPDATE guests
            SET status = 'waiting'
            WHERE status = 'idle'
        ");

        // PostgreSQL doesn't support removing enum values directly
        // Create a new enum type without 'idle'
        DB::statement("ALTER TYPE guests_status_enum RENAME TO guests_status_enum_old");
        DB::statement("CREATE TYPE guests_status_enum AS ENUM ('waiting', 'active', 'banned')");
        DB::statement("ALTER TABLE guests ALTER COLUMN status TYPE guests_status_enum USING status::text::guests_status_enum");
        DB::statement("DROP TYPE guests_status_enum_old");
    }
};
