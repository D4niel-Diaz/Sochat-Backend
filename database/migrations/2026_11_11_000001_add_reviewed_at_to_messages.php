<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When an admin reviewed a flagged message. Unreviewed flagged messages
        // are kept by chats:prune-messages until then (or the hard limit).
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('reviewed_at');
        });
    }
};
