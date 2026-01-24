<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // Change enum to include 'idle' status
            $table->enum('status', ['idle', 'waiting', 'active', 'banned'])
                  ->default('idle')
                  ->change();
        });

        // Update existing guests with 'waiting' or 'active' status to remain
        // Set any guests with invalid status to 'idle'
        DB::statement("
            UPDATE guests 
            SET status = 'idle' 
            WHERE status NOT IN ('idle', 'waiting', 'active', 'banned')
        ");
    }

    public function down(): void
    {
        // Update idle guests back to waiting
        DB::statement("
            UPDATE guests 
            SET status = 'waiting' 
            WHERE status = 'idle'
        ");

        Schema::table('guests', function (Blueprint $table) {
            $table->enum('status', ['waiting', 'active', 'banned'])
                  ->default('waiting')
                  ->change();
        });
    }
};
