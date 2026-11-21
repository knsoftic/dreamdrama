<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('duration_days')->default(30);     // 0 = never expires
            $table->unsignedInteger('daily_cycle_limit')->default(3);
            $table->decimal('rate_multiplier', 5, 2)->default(1);
            $table->decimal('daily_earning_cap', 12, 2)->default(0);   // 0 = no cap
            $table->decimal('min_withdrawal', 12, 2)->default(500);
            $table->string('color', 20)->default('#8b5cf6');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 40);
            $table->string('transaction_id', 100);
            $table->string('sender_number', 40)->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('status', 20)->default('pending');   // pending | approved | rejected
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();             // null = never expires
            $table->string('status', 20)->default('active');      // active | expired | cancelled
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 60)->nullable();
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ad_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('network', 30)->default('adsterra');    // adsterra | monetag | custom
            $table->string('placement', 30)->default('ad_break');  // ad_break | page_banner
            $table->longText('code');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->timestamps();
        });

        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('in_progress');  // in_progress | completed
            $table->unsignedInteger('videos_required');
            $table->unsignedInteger('ads_required');
            $table->unsignedInteger('videos_watched')->default(0);
            $table->unsignedInteger('ads_watched')->default(0);
            $table->unsignedInteger('video_seconds')->default(0);
            $table->decimal('rate_per_second', 12, 6);
            $table->decimal('rate_per_ad', 12, 4);
            $table->decimal('rate_multiplier', 5, 2)->default(1);
            $table->decimal('video_earning', 14, 4)->default(0);
            $table->decimal('ad_earning', 14, 4)->default(0);
            $table->decimal('total_earning', 14, 4)->default(0);
            $table->decimal('credited_amount', 14, 4)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'completed_at']);
        });

        Schema::create('watch_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('token')->unique();
            $table->boolean('is_playing')->default(true);
            $table->boolean('is_earning')->default(true);       // false = already earned on this video, or limit reached
            $table->timestamp('last_heartbeat_at');
            $table->unsignedInteger('counted_seconds')->default(0);
            $table->boolean('counted')->default(false);         // true = counted as a watched video in the cycle
            $table->decimal('earning', 14, 4)->default(0);
            $table->timestamp('ended_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'video_id']);
            $table->index(['user_id', 'ended_at']);
        });

        Schema::create('ad_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('required_seconds');
            $table->string('status', 20)->default('started');   // started | completed
            $table->decimal('earning', 14, 4)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);           // credit | debit
            $table->string('source', 40);         // cycle | withdrawal | withdrawal_refund | admin
            $table->decimal('amount', 14, 4);
            $table->decimal('balance_after', 14, 4);
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 40);
            $table->string('account_title', 100);
            $table->string('account_number', 60);
            $table->string('status', 20)->default('pending');   // pending | paid | rejected
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fraud_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->text('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('revenue_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('network', 30);
            $table->decimal('amount', 12, 2);    // in site currency
            $table->unsignedBigInteger('impressions')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('date');
        });
    }

    public function down(): void
    {
        foreach (['revenue_entries', 'fraud_logs', 'withdrawals', 'wallet_transactions', 'ad_views', 'watch_sessions',
                  'cycles', 'ad_units', 'videos', 'subscriptions', 'payments', 'plans', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
