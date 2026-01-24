<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->enum('status', ['waiting', 'active', 'idle', 'banned'])->default('waiting')->change();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->enum('status', ['waiting', 'active', 'banned'])->default('waiting')->change();
        });
    }
};
