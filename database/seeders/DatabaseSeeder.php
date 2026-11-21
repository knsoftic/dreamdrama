<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates the admin account (from ADMIN_* values in .env) and starter plans.
     * Safe to run again: existing rows are updated, not duplicated.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free', 'name' => 'Free', 'price' => 0, 'duration_days' => 0, 'daily_cycle_limit' => 3,
                'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 500, 'color' => '#94a3b8',
                'description' => 'Shuru karne ke liye — roz 3 cycles.', 'is_default' => true, 'sort_order' => 1,
            ],
            [
                'slug' => 'silver', 'name' => 'Silver', 'price' => 500, 'duration_days' => 30, 'daily_cycle_limit' => 10,
                'rate_multiplier' => 1.5, 'daily_earning_cap' => 0, 'min_withdrawal' => 300, 'color' => '#38bdf8',
                'description' => 'Zyada cycles aur 1.5x earning rate.', 'is_default' => false, 'sort_order' => 2,
            ],
            [
                'slug' => 'gold', 'name' => 'Gold', 'price' => 1500, 'duration_days' => 30, 'daily_cycle_limit' => 25,
                'rate_multiplier' => 2, 'daily_earning_cap' => 0, 'min_withdrawal' => 200, 'color' => '#fbbf24',
                'description' => 'Sab se zyada cycles aur double earning.', 'is_default' => false, 'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan + ['is_active' => true]);
        }

        $email = env('ADMIN_EMAIL', 'admin@playearn.test');
        $admin = User::query()->firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => env('ADMIN_NAME', 'Admin'),
            'phone' => $admin->phone ?? '00000000000',
            'password' => env('ADMIN_PASSWORD', 'change-me'),
        ]);
        $admin->role = 'admin';
        $admin->status = 'active';
        $admin->save();

        if (! $admin->subscriptions()->exists()) {
            Subscription::query()->create([
                'user_id' => $admin->id,
                'plan_id' => Plan::defaultPlan()->id,
                'starts_at' => now(),
                'status' => 'active',
            ]);
        }

        $this->command?->info("Admin ready: {$email} (password: ADMIN_PASSWORD in .env)");
    }
}
