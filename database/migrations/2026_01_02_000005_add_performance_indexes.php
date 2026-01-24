<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Messages table indexes for common query patterns
        Schema::table('messages', function (Blueprint $table) {
            // Composite index for fetching messages by chat with ordering
            $table->index(['chat_id', 'created_at'], 'idx_chat_created_at');
            
            // Composite index for fetching messages by sender with ordering
            $table->index(['sender_guest_id', 'created_at'], 'idx_sender_created_at');
            
            // Index for flagged messages query
            $table->index(['is_flagged', 'created_at'], 'idx_flagged_created_at');
        });

        // Guests table indexes for status queries
        Schema::table('guests', function (Blueprint $table) {
            // Composite index for finding waiting guests
            $table->index(['status', 'expires_at', 'created_at'], 'idx_status_expires_created');
            
            // Composite index for active guests
            $table->index(['status', 'expires_at'], 'idx_status_expires');
        });

        // Chats table indexes for active chat queries
        Schema::table('chats', function (Blueprint $table) {
            // Composite index for finding active chats by user
            $table->index(['guest_id_1', 'status', 'ended_at'], 'idx_guest1_status_ended');
            $table->index(['guest_id_2', 'status', 'ended_at'], 'idx_guest2_status_ended');
        });

        // Reports table indexes for admin queries
        Schema::table('reports', function (Blueprint $table) {
            // Composite index for pending reports
            $table->index(['status', 'created_at'], 'idx_status_created');
            
            // Note: idx_reported_status conflicts with foreign key, skipping
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_chat_created_at');
            $table->dropIndex('idx_sender_created_at');
            $table->dropIndex('idx_flagged_created_at');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex('idx_status_expires_created');
            $table->dropIndex('idx_status_expires');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex('idx_guest1_status_ended');
            $table->dropIndex('idx_guest2_status_ended');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('idx_status_created');
        });
    }
};
