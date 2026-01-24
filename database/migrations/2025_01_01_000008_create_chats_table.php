<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id('chat_id');
            $table->uuid('guest_id_1');
            $table->uuid('guest_id_2');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->uuid('ended_by')->nullable();
            $table->enum('status', ['active', 'ended'])->default('active');
            $table->timestamps();

            $table->foreign('guest_id_1')->references('guest_id')->on('guests')->onDelete('cascade');
            $table->foreign('guest_id_2')->references('guest_id')->on('guests')->onDelete('cascade');
            $table->foreign('ended_by')->references('guest_id')->on('guests')->onDelete('set null');
            $table->index('guest_id_1');
            $table->index('guest_id_2');
            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
