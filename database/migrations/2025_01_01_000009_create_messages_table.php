<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id('message_id');
            $table->unsignedBigInteger('chat_id');
            $table->uuid('sender_guest_id');
            $table->text('content');
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();

            $table->foreign('chat_id')->references('chat_id')->on('chats')->onDelete('cascade');
            $table->foreign('sender_guest_id')->references('guest_id')->on('guests')->onDelete('cascade');
            $table->index('chat_id');
            $table->index('created_at');
            $table->index('sender_guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
