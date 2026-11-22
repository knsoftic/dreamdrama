<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubAdminCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(['payment_mode' => 'manual', 'subadmin_min_withdrawal' => '100', 'max_accounts_per_ip' => '0']);
        $this->plan = Plan::query()->create([
            'name' => 'Gold', 'slug' => 'gold', 'price' => 1000, 'duration_days' => 30, 'daily_cycle_limit' => 10,
            'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 100, 'is_default' => false, 'is_active' => true,
        ]);
        Plan::query()->create([
            'name' => 'Free', 'slug' => 'free', 'price' => 0, 'duration_days' => 0, 'daily_cycle_limit' => 3,
            'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 500, 'is_default' => true, 'is_active' => true,
        ]);
    }

    private function subAdmin(float $tax): User
    {
        $u = User::query()->create(['name' => 'Boss', 'phone' => '0300'.random_int(1000000, 9999999), 'password' => 'secret123']);
        $u->forceFill(['role' => 'sub_admin', 'referral_code' => 'BOSS'.random_int(100, 999), 'commission_percent' => $tax])->save();

        return $u;
    }

    private function userUnder(User $sub): User
    {
        return User::query()->create([
            'name' => 'Buyer', 'phone' => '0301'.random_int(1000000, 9999999), 'password' => 'secret123',
            'referred_by' => $sub->id, 'sub_admin_id' => $sub->id,
        ]);
    }

    private function admin(): User
    {
        $u = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    private function buy(User $buyer): Payment
    {
        $this->actingAs($buyer)->post(route('plans.pay', $this->plan), [
            'method' => 'JazzCash', 'transaction_id' => 'TID'.random_int(1000, 9999),
        ]);

        return $buyer->payments()->latest('id')->first();
    }

    public function test_deposit_snapshots_tax_and_credits_subadmin_on_approval(): void
    {
        $boss = $this->subAdmin(20);
        $buyer = $this->userUnder($boss);
        $admin = $this->admin();

        $payment = $this->buy($buyer);
        $this->assertSame($boss->id, $payment->sub_admin_id);
        $this->assertEqualsWithDelta(20.0, (float) $payment->tax_percent, 0.001);

        // Not credited until approved.
        $this->assertEqualsWithDelta(0, (float) $boss->fresh()->balance, 0.001);

        $this->actingAs($admin)->post(route('admin.payments.approve', $payment))->assertRedirect();

        // 1000 - 20% tax = 800 credited to the sub-admin.
        $this->assertEqualsWithDelta(800, (float) $boss->fresh()->balance, 0.01);
        $this->assertEqualsWithDelta(800, (float) $boss->fresh()->total_earned, 0.01);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $boss->id, 'source' => 'commission', 'amount' => 800]);
    }

    public function test_tax_change_only_affects_new_deposits(): void
    {
        $boss = $this->subAdmin(20);
        $buyer = $this->userUnder($boss);
        $admin = $this->admin();

        $first = $this->buy($buyer);
        $this->actingAs($admin)->post(route('admin.payments.approve', $first));   // net 800

        $boss->update(['commission_percent' => 50]);   // change tax

        $second = $this->buy($buyer);
        $this->assertEqualsWithDelta(50.0, (float) $second->tax_percent, 0.001);   // new snapshot
        $this->assertEqualsWithDelta(20.0, (float) $first->fresh()->tax_percent, 0.001);   // old unchanged

        $this->actingAs($admin)->post(route('admin.payments.approve', $second));   // net 500

        $this->assertEqualsWithDelta(1300, (float) $boss->fresh()->balance, 0.01);   // 800 + 500
    }

    public function test_subadmin_can_withdraw_from_balance(): void
    {
        $boss = $this->subAdmin(10);
        $buyer = $this->userUnder($boss);
        $pay = $this->buy($buyer);
        $this->actingAs($this->admin())->post(route('admin.payments.approve', $pay));   // net 900

        $this->actingAs($boss->fresh())->get(route('team.withdrawals.index'))->assertOk()->assertSee('900');

        $this->actingAs($boss->fresh())->post(route('team.withdrawals.store'), [
            'amount' => 500, 'method' => 'JazzCash', 'account_title' => 'Boss', 'account_number' => '03001234567',
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(400, (float) $boss->fresh()->balance, 0.01);
        $this->assertDatabaseHas('withdrawals', ['user_id' => $boss->id, 'amount' => 500, 'status' => 'pending']);

        // Cannot withdraw more than the balance (now 400 left, one pending already).
        $this->actingAs($boss->fresh())->post(route('team.withdrawals.store'), [
            'amount' => 999, 'method' => 'JazzCash', 'account_title' => 'Boss', 'account_number' => '03001234567',
        ])->assertSessionHasErrors('amount');
    }

    public function test_deposits_page_and_dashboard_load(): void
    {
        $boss = $this->subAdmin(15);
        $pay = $this->buy($this->userUnder($boss));
        $this->actingAs($this->admin())->post(route('admin.payments.approve', $pay));

        $this->actingAs($boss)->get(route('team.dashboard'))->assertOk()->assertSee('Deposits');
        $this->actingAs($boss)->get(route('team.deposits.index'))->assertOk()->assertSee('Your net');
    }
}
