<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();    // the member
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();   // their sub-admin (or super-admin)
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('user_unread')->default(0);
            $table->unsignedInteger('staff_unread')->default(0);
            $table->boolean('muted')->default(false);      // staff muted -> no push to staff
            $table->boolean('blocked')->default(false);    // staff blocked -> member cannot send
            $table->timestamps();
            $table->unique(['user_id', 'staff_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10)->default('text');   // text | image | voice
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->json('meta')->nullable();              // e.g. voice duration, image size
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
