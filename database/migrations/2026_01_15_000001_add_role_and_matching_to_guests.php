<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->enum('role', ['tutor', 'learner'])->nullable()->after('status');
            $table->string('subject', 100)->nullable()->after('role');
            $table->json('availability')->nullable()->after('subject');
            
            // Index for matching queries
            $table->index(['role', 'subject'], 'idx_guests_role_subject');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex('idx_guests_role_subject');
            $table->dropColumn(['role', 'subject', 'availability']);
        });
    }
};
