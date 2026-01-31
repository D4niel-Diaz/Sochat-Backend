<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presence', function (Blueprint $table) {
            $table->enum('role', ['tutor', 'learner'])->nullable()->after('is_waiting');
            $table->string('subject', 100)->nullable()->after('role');
            $table->json('availability')->nullable()->after('subject');
            
            // Composite index for fast matching queries
            // This index optimizes: WHERE role=X AND subject=Y AND is_waiting=1 AND is_online=1 AND expires_at>NOW()
            $table->index(['role', 'subject', 'is_waiting', 'is_online', 'expires_at'], 'idx_presence_matching');
        });
    }

    public function down(): void
    {
        Schema::table('presence', function (Blueprint $table) {
            $table->dropIndex('idx_presence_matching');
            $table->dropColumn(['role', 'subject', 'availability']);
        });
    }
};
