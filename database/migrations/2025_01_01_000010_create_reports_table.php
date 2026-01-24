<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->unsignedBigInteger('chat_id');
            $table->uuid('reporter_guest_id');
            $table->uuid('reported_guest_id');
            $table->text('reason');
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['pending', 'reviewing', 'resolved'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('chat_id')->references('chat_id')->on('chats')->onDelete('cascade');
            $table->foreign('reporter_guest_id')->references('guest_id')->on('guests')->onDelete('cascade');
            $table->foreign('reported_guest_id')->references('guest_id')->on('guests')->onDelete('cascade');
            $table->index('chat_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
