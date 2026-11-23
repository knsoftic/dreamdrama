<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source_title');
            $table->string('source_path')->nullable();
            $table->string('status', 20)->default('queued');   // queued | processing | done | failed
            $table->json('options')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('done')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('short_batch_id')->nullable()->after('uploaded_by')->constrained('short_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('short_batch_id');
        });
        Schema::dropIfExists('short_batches');
    }
};
