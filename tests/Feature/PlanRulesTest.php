<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanRulesTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $attrs = []): Plan
    {
        static $n = 0;
        $n++;

        return Plan::query()->create($attrs + [
            'name' => "Plan $n", 'slug' => "plan-$n", 'price' => 0, 'duration_days' => 0, 'daily_cycle_limit' => 3,
            'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 100, 'max_withdrawal' => 0,
            'withdrawals_enabled' => true, 'withdrawal_gap_days' => 0, 'required_referrals' => 0,
        ]);
    }

    private function userOn(Plan $plan, float $balance = 0, array $attrs = []): User
    {
        static $phone = 3001000000;
        $user = User::query()->create($attrs + ['name' => 'User '.$phone, 'phone' => '0'.($phone++), 'password' => 'secret123']);
        Subscription::query()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'starts_at' => now(), 'status' => 'active']);
        if ($balance > 0) {
            app(WalletService::class)->credit($user, $balance, 'admin', null, 'Test');
        }

        return $user->fresh();
    }

    private function withdraw(User $user, float $amount)
    {
        return $this->actingAs($user)->post(route('wallet.withdraw'), [
            'amount' => $amount, 'method' => 'JazzCash', 'account_title' => 'Test', 'account_number' => '03001234567',
        ]);
    }

    private function completeCycle(User $user): void
    {
        Cycle::query()->create([
            'user_id' => $user->id, 'status' => 'completed', 'videos_required' => 1, 'ads_required' => 0,
            'rate_per_second' => 0, 'rate_per_ad' => 0, 'completed_at' => now(),
        ]);
    }

    public function test_plan_without_withdrawals_blocks_request(): void
    {
        $user = $this->userOn($this->plan(['withdrawals_enabled' => false]), 1000);

        $this->withdraw($user, 500)->assertSessionHasErrors('amount');
        $this->assertEqualsWithDelta(1000, (float) $user->fresh()->balance, 0.001);
        $this->actingAs($user)->get(route('wallet'))->assertOk()->assertSee('does not allow withdrawals');
    }

    public function test_max_withdrawal_per_request(): void
    {
        $user = $this->userOn($this->plan(['max_withdrawal' => 300]), 1000);

        $this->withdraw($user, 500)->assertSessionHasErrors('amount');
        $this->withdraw($user, 300)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(700, (float) $user->fresh()->balance, 0.001);
    }

    public function test_gap_days_between_withdrawals(): void
    {
        $user = $this->userOn($this->plan(['withdrawal_gap_days' => 7]), 1000);

        $this->withdraw($user, 200)->assertSessionHasNoErrors();
        $user->withdrawals()->update(['status' => 'paid']);

        $this->withdraw($user, 200)->assertSessionHasErrors('amount');   // too soon
        $this->travel(8)->days();
        $this->withdraw($user, 200)->assertSessionHasNoErrors();
    }

    public function test_required_active_referrals(): void
    {
        Setting::put(['referral_min_cycles' => '1']);
        $plan = $this->plan(['required_referrals' => 2]);
        $user = $this->userOn($plan, 1000);

        $a = $this->userOn($plan, 0, ['referred_by' => null]);
        $b = $this->userOn($plan, 0);
        $a->forceFill(['referred_by' => $user->id])->save();
        $b->forceFill(['referred_by' => $user->id])->save();

        $this->completeCycle($a);
        $this->withdraw($user, 200)->assertSessionHasErrors('amount');   // only 1 of 2 active

        $b->forceFill(['is_flagged' => true])->save();
        $this->completeCycle($b);
        $this->withdraw($user, 200)->assertSessionHasErrors('amount');   // flagged invite does not count

        $b->forceFill(['is_flagged' => false])->save();
        $this->withdraw($user, 200)->assertSessionHasNoErrors();
    }

    public function test_register_with_invite_link_and_code(): void
    {
        $this->plan(['is_default' => true]);
        $inviter = $this->userOn(Plan::query()->first());
        $inviter->forceFill(['register_ip' => '127.0.0.1'])->save();   // same IP as the test request
        $this->assertNotEmpty($inviter->referral_code);

        $this->get(route('invite.link', strtolower($inviter->referral_code)))->assertRedirect(route('register'));
        $this->get(route('register'))->assertOk()->assertSee($inviter->referral_code);

        $this->post(route('register'), [
            'name' => 'Friend', 'phone' => '03111111111', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'ref' => strtolower($inviter->referral_code),
        ])->assertRedirect(route('feed'));

        $this->assertSame($inviter->id, User::query()->where('phone', '03111111111')->value('referred_by'));
        $this->assertDatabaseHas('fraud_logs', ['type' => 'self_referral']);   // same IP in tests

        auth()->logout();
        $this->post(route('register'), [
            'name' => 'Other', 'phone' => '03122222222', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'ref' => 'NOPE123',
        ])->assertSessionHasErrors('ref');
    }

    public function test_plan_overrides_videos_and_ads_per_cycle(): void
    {
        Setting::put(['videos_per_cycle' => '10', 'ads_per_cycle' => '10']);
        $user = $this->userOn($this->plan(['videos_per_cycle' => 3, 'ads_per_cycle' => 0]));
        $video = Video::query()->create(['title' => 'V', 'file_path' => 'videos/v.mp4', 'duration_seconds' => 20, 'is_active' => true]);

        $state = $this->actingAs($user)->postJson(route('app.watch.start'), ['video_id' => $video->id])->json('state');
        $this->assertSame(3, $state['cycle']['videos_required']);
        $this->assertSame(0, $state['cycle']['ads_required']);
    }

    public function test_admin_saves_plan_rules(): void
    {
        $admin = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $admin->forceFill(['role' => 'admin'])->save();
        $plan = $this->plan();

        $payload = [
            'name' => 'Diamond', 'price' => 3000, 'duration_days' => 60, 'daily_cycle_limit' => 40,
            'videos_per_cycle' => 8, 'ads_per_cycle' => 4, 'rate_multiplier' => 3, 'daily_earning_cap' => 0,
            'min_withdrawal' => 100, 'max_withdrawal' => 50000, 'withdrawal_gap_days' => 0, 'required_referrals' => 0,
            'withdrawals_enabled' => '1', 'color' => '#22c55e', 'is_active' => '1',
        ];
        $this->actingAs($admin)->put(route('admin.plans.update', $plan), $payload)->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame(8, $plan->videos_per_cycle);
        $this->assertSame(320, $plan->dailyVideos());
        $this->assertEqualsWithDelta(50000, (float) $plan->max_withdrawal, 0.01);

        // max below min is refused
        $this->actingAs($admin)->put(route('admin.plans.update', $plan), ['max_withdrawal' => 50] + $payload)
            ->assertSessionHasErrors('max_withdrawal');

        $this->actingAs($admin)->get(route('admin.plans.edit', $plan))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.show', $admin))->assertOk();
        $this->actingAs($admin)->get(route('invite'))->assertOk();
    }
}
