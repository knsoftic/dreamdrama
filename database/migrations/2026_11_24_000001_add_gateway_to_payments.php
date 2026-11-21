<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway', 20)->nullable()->after('plan_id');          // null/manual | pkpay
            $table->string('gateway_payment_id', 80)->nullable()->after('gateway')->index();
            $table->string('gateway_status', 20)->nullable()->after('status');     // pending|paid|expired|failed|cancelled
            $table->text('checkout_url')->nullable()->after('screenshot_path');
            $table->timestamp('expires_at')->nullable()->after('checkout_url');
            $table->timestamp('paid_at')->nullable()->after('expires_at');
            $table->json('gateway_meta')->nullable()->after('paid_at');            // safe snapshot, never secrets
        });

        // transaction_id is filled by the gateway later, so allow it to be empty at creation.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_id', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'gateway_payment_id', 'gateway_status', 'checkout_url', 'expires_at', 'paid_at', 'gateway_meta']);
        });
    }
};
