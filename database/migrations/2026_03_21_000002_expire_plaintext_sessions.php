<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SECURITY MIGRATION: Re-hash all existing plain-text session tokens.
 *
 * Previously, session_token was stored as raw hex (64 chars from bin2hex(random_bytes(32))).
 * It is now stored as a SHA-256 hash of that raw token.
 *
 * IMPORTANT: This migration invalidates ALL currently active guest sessions.
 * Users will need to re-create their session. Since guests are anonymous and
 * sessions expire within 24 hours, the impact is minimal.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only migrate rows that look like raw hex tokens (64-char hex string)
        // SHA-256 hashes are also 64-char hex, but we detect un-hashed tokens
        // by checking: if the token can be decoded as raw hex with 32 bytes,
        // it's likely still raw. Since we can't reliably distinguish after the fact,
        // the safest approach is to expire all existing sessions immediately —
        // they'll get new (hashed) tokens on next visit.
        DB::table('guests')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()->subSecond()]);

        // Also clear the presence table to prevent orphaned waiting users
        // with invalidated sessions from clogging the matching pool
        DB::table('presence')->truncate();
    }

    public function down(): void
    {
        // Cannot reverse token hashing — this is intentionally irreversible.
        // Rolling back only restores the presence table truncation.
    }
};
