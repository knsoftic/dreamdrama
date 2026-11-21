<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('videos_per_cycle')->nullable()->after('daily_cycle_limit');   // null = global setting
            $table->unsignedInteger('ads_per_cycle')->nullable()->after('videos_per_cycle');       // null = global setting
            $table->boolean('withdrawals_enabled')->default(true)->after('daily_earning_cap');
            $table->decimal('max_withdrawal', 12, 2)->default(0)->after('min_withdrawal');        // 0 = no maximum
            $table->unsignedInteger('withdrawal_gap_days')->default(0)->after('max_withdrawal');   // days between withdrawals
            $table->unsignedInteger('required_referrals')->default(0)->after('withdrawal_gap_days'); // active invites needed
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique()->after('phone');
            $table->unsignedBigInteger('referred_by')->nullable()->index()->after('referral_code');
        });

        // Every existing user gets an invite code.
        foreach (DB::table('users')->whereNull('referral_code')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['referral_code' => $this->code()]);
        }

        // Starter rules for the starter plans: smaller plan = more limits.
        // Only the new columns are filled; prices etc. the admin changed stay as they are.
        $rules = [
            'free' => ['withdrawals_enabled' => false, 'max_withdrawal' => 0, 'withdrawal_gap_days' => 0, 'required_referrals' => 0],
            'silver' => ['withdrawals_enabled' => true, 'max_withdrawal' => 2000, 'withdrawal_gap_days' => 7, 'required_referrals' => 3],
            'gold' => ['withdrawals_enabled' => true, 'max_withdrawal' => 10000, 'withdrawal_gap_days' => 1, 'required_referrals' => 1],
        ];
        foreach ($rules as $slug => $values) {
            DB::table('plans')->where('slug', $slug)->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropIndex(['referred_by']);
            $table->dropColumn(['referral_code', 'referred_by']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['videos_per_cycle', 'ads_per_cycle', 'withdrawals_enabled', 'max_withdrawal', 'withdrawal_gap_days', 'required_referrals']);
        });
    }

    private function code(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 7; $i++) {
                $code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while (DB::table('users')->where('referral_code', $code)->exists());

        return $code;
    }
};
