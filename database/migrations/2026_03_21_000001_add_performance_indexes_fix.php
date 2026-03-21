<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite and individual indexes that make the hot query paths fast:
 *
 * 1. presence: composite index on (is_waiting, is_online, expires_at, guest_id)
 *    used by ChatService::findMatch() for the partner-selection scan.
 *
 * 2. chats: individual indexes on guest_id_1 and guest_id_2 separately,
 *    used by ChatRepository::findActiveByGuestId() — the existing unique
 *    constraint on (guest_id_1, guest_id_2) does NOT help for single-column
 *    lookups in MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only run against MySQL — SQLite in tests handles indexes differently
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Presence table — composite index for matching query performance
        if (!$this->indexExists('presence', 'idx_presence_matching')) {
            DB::statement('
                CREATE INDEX idx_presence_matching
                ON presence (is_waiting, is_online, expires_at, guest_id)
            ');
        }

        // Chats table — individual indexes for single-column lookups
        if (!$this->indexExists('chats', 'idx_chats_guest_id_1')) {
            DB::statement('CREATE INDEX idx_chats_guest_id_1 ON chats (guest_id_1, status)');
        }
        if (!$this->indexExists('chats', 'idx_chats_guest_id_2')) {
            DB::statement('CREATE INDEX idx_chats_guest_id_2 ON chats (guest_id_2, status)');
        }
        if (!$this->indexExists('chats', 'idx_chats_status')) {
            DB::statement('CREATE INDEX idx_chats_status ON chats (status, ended_at)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['idx_presence_matching'] as $idx) {
            if ($this->indexExists('presence', $idx)) {
                DB::statement("DROP INDEX {$idx} ON presence");
            }
        }

        foreach (['idx_chats_guest_id_1', 'idx_chats_guest_id_2', 'idx_chats_status'] as $idx) {
            if ($this->indexExists('chats', $idx)) {
                DB::statement("DROP INDEX {$idx} ON chats");
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SELECT COUNT(*) as cnt
             FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name   = ?
               AND index_name   = ?",
            [$table, $indexName]
        );

        return ($result[0]->cnt ?? 0) > 0;
    }
};
