<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates the starter plans (only the ones that don't exist yet, so admin
     * edits are never overwritten). Safe to run on every deploy.
     *
     * For local development it also creates an admin from ADMIN_EMAIL /
     * ADMIN_PASSWORD in .env. On the live server the installer creates the
     * admin with `php artisan app:admin` instead.
     */
    public function run(): void
    {
        $plans = [
            // Smaller plan = more limits, bigger plan = fewer limits.
            [
                'slug' => 'free', 'name' => 'Free', 'price' => 0, 'duration_days' => 0, 'daily_cycle_limit' => 3,
                'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 500, 'color' => '#94a3b8',
                'withdrawals_enabled' => false, 'max_withdrawal' => 0, 'withdrawal_gap_days' => 0, 'required_referrals' => 0,
                'description' => 'Try karne ke liye — earning hoti hai, withdrawal ke liye plan upgrade karein.', 'is_default' => true, 'sort_order' => 1,
            ],
            [
                'slug' => 'silver', 'name' => 'Silver', 'price' => 500, 'duration_days' => 30, 'daily_cycle_limit' => 10,
                'rate_multiplier' => 1.5, 'daily_earning_cap' => 0, 'min_withdrawal' => 300, 'color' => '#38bdf8',
                'withdrawals_enabled' => true, 'max_withdrawal' => 2000, 'withdrawal_gap_days' => 7, 'required_referrals' => 3,
                'description' => 'Zyada cycles aur 1.5x earning rate.', 'is_default' => false, 'sort_order' => 2,
            ],
            [
                'slug' => 'gold', 'name' => 'Gold', 'price' => 1500, 'duration_days' => 30, 'daily_cycle_limit' => 25,
                'rate_multiplier' => 2, 'daily_earning_cap' => 0, 'min_withdrawal' => 200, 'color' => '#fbbf24',
                'withdrawals_enabled' => true, 'max_withdrawal' => 10000, 'withdrawal_gap_days' => 1, 'required_referrals' => 1,
                'description' => 'Sab se zyada cycles, double earning aur sab se kam shartein.', 'is_default' => false, 'sort_order' => 3,
            ],
        ];

        if (! Plan::query()->exists()) {
            foreach ($plans as $plan) {
                Plan::query()->create($plan + ['is_active' => true]);
            }
        }

        $password = (string) env('ADMIN_PASSWORD', '');
        if ($password !== '' && $password !== 'change-me' && env('ADMIN_EMAIL')) {
            putenv('SETUP_ADMIN_PASSWORD='.$password);
            Artisan::call('app:admin', ['--email' => env('ADMIN_EMAIL'), '--name' => env('ADMIN_NAME', 'Admin')]);
            putenv('SETUP_ADMIN_PASSWORD');
            $this->command?->info(trim(Artisan::output()));
        }
    }
}
