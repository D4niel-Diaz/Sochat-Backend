<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->uuid('guest_id')->primary();
            $table->string('session_token', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['waiting', 'active', 'banned'])->default('waiting');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('expires_at');
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
