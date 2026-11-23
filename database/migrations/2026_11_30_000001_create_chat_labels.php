<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();   // null = global (super-admin)
            $table->string('name', 40);
            $table->string('color', 20)->default('#8b5cf6');
            $table->timestamps();
        });

        Schema::create('conversation_label', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('chat_labels')->cascadeOnDelete();
            $table->primary(['conversation_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_label');
        Schema::dropIfExists('chat_labels');
    }
};
