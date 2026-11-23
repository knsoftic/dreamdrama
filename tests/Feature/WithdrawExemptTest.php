<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\WithdrawalRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawExemptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(['withdrawals_enabled' => '1', 'withdraw_methods' => 'JazzCash', 'max_accounts_per_ip' => '0']);
        Plan::query()->create([
            'name' => 'Free', 'slug' => 'free', 'price' => 0, 'duration_days' => 0, 'daily_cycle_limit' => 3,
            'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 500, 'is_default' => true,
            'is_active' => true, 'withdrawals_enabled' => false,
        ]);
    }

    private function user(float $balance = 50): User
    {
        $u = User::query()->create(['name' => 'U', 'phone' => '0300'.random_int(1000000, 9999999), 'password' => 'secret123']);
        $u->forceFill(['balance' => $balance])->save();

        return $u;
    }

    public function test_free_user_blocked_but_exempt_passes_all_criteria(): void
    {
        $rules = app(WithdrawalRules::class);
        $user = $this->user(50);

        $this->assertFalse($rules->check($user)['allowed']);   // Free plan + low balance

        $user->update(['withdraw_exempt' => true]);
        $check = $rules->check($user->fresh());

        $this->assertTrue($check['allowed']);
        $this->assertSame(1.0, $check['min']);
        $this->assertStringContainsString('criteria complete', strtolower($check['checks'][0]['label']));
    }

    public function test_admin_toggles_the_permission(): void
    {
        $admin = User::query()->create(['name' => 'A', 'phone' => '03000000000', 'password' => 'secret123']);
        $admin->forceFill(['role' => 'admin'])->save();
        $user = $this->user();

        $this->actingAs($admin)->post(route('admin.users.withdraw-exempt', $user))->assertRedirect();
        $this->assertTrue($user->fresh()->withdraw_exempt);

        $this->actingAs($admin)->post(route('admin.users.withdraw-exempt', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->withdraw_exempt);
    }

    public function test_exempt_user_can_submit_a_withdrawal(): void
    {
        $user = $this->user(300);
        $user->update(['withdraw_exempt' => true]);

        $this->actingAs($user)->post(route('wallet.withdraw'), [
            'amount' => 100, 'method' => 'JazzCash', 'account_title' => 'U', 'account_number' => '03001234567',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('withdrawals', ['user_id' => $user->id, 'amount' => 100, 'status' => 'pending']);
    }
}
