<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence', function (Blueprint $table) {
            $table->string('guest_id')->primary();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_waiting')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['is_online', 'expires_at']);
            $table->index(['is_waiting', 'is_online', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence');
    }
};
