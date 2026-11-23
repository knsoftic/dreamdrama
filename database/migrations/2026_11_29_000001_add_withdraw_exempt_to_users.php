<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin can let a specific user withdraw directly, bypassing all criteria.
            $table->boolean('withdraw_exempt')->default(false)->after('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('withdraw_exempt');
        });
    }
};
