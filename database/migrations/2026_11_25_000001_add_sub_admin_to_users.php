<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The sub-admin who owns this user's network (null = under the super admin).
            $table->unsignedBigInteger('sub_admin_id')->nullable()->after('referred_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sub_admin_id']);
            $table->dropColumn('sub_admin_id');
        });
    }
};
