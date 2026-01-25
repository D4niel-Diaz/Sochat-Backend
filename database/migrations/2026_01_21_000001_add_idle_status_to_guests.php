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
            $table->enum('status', ['idle', 'waiting', 'active', 'banned'])->change();
        });
    }

    public function down(): void
    {
        // Update idle guests back to waiting before changing enum
        DB::statement("
            UPDATE guests
            SET status = 'waiting'
            WHERE status = 'idle'
        ");

        Schema::table('guests', function (Blueprint $table) {
            $table->enum('status', ['waiting', 'active', 'banned'])->change();
        });
    }
};
