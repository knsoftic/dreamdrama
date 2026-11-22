<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Platform tax % the super admin keeps from a sub-admin's deposits.
            $table->decimal('commission_percent', 5, 2)->default(0)->after('sub_admin_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            // The sub-admin who owns this deposit, and the tax % at the time it
            // was made (so changing the % later never touches old payments).
            $table->unsignedBigInteger('sub_admin_id')->nullable()->after('plan_id')->index();
            $table->decimal('tax_percent', 5, 2)->default(0)->after('amount');
            $table->timestamp('callback_at')->nullable()->after('paid_at');   // last verified gateway callback
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['sub_admin_id']);
            $table->dropColumn(['sub_admin_id', 'tax_percent', 'callback_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });
    }
};
