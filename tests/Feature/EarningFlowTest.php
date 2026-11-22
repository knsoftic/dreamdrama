<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();   // watch time is measured from timestamps, so keep the clock exact

        Setting::put([
            'videos_per_cycle' => '2',
            'ads_per_cycle' => '2',
            'ad_placement' => 'spread',
            'rate_per_second' => '0.01',
            'rate_per_ad' => '0.05',
            'min_watch_seconds' => '5',
            'heartbeat_seconds' => '5',
            'ad_duration_seconds' => '15',
            'earn_once_per_video' => '1',
        ]);

        $this->plan = Plan::query()->create([
            'name' => 'Free', 'slug' => 'free', 'price' => 0, 'duration_days' => 0, 'daily_cycle_limit' => 2,
            'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 500, 'is_default' => true,
        ]);

        $this->user = User::query()->create(['name' => 'Test User', 'phone' => '03001234567', 'password' => 'secret123']);
        Subscription::query()->create(['user_id' => $this->user->id, 'plan_id' => $this->plan->id, 'starts_at' => now(), 'status' => 'active']);

        foreach ([1, 2, 3] as $i) {
            Video::query()->create(['title' => "Video $i", 'file_path' => "videos/$i.mp4", 'duration_seconds' => 20, 'is_active' => true]);
        }
    }

    private function start(int $videoId): array
    {
        return $this->actingAs($this->user)->postJson(route('app.watch.start'), ['video_id' => $videoId])->assertOk()->json();
    }

    private function beat(string $token, bool $playing = true): array
    {
        return $this->actingAs($this->user)->postJson(route('app.watch.heartbeat'), ['token' => $token, 'playing' => $playing])->assertOk()->json();
    }

    private function end(string $token): array
    {
        return $this->actingAs($this->user)->postJson(route('app.watch.end'), ['token' => $token])->assertOk()->json();
    }

    private function watchAd(): array
    {
        $ad = $this->actingAs($this->user)->postJson(route('app.ad.start'))->assertOk()->json();
        $this->assertSame('ok', $ad['status']);
        $this->travel(15)->seconds();

        return $this->actingAs($this->user)->postJson(route('app.ad.complete'), ['ad_view_id' => $ad['ad_view_id']])->assertOk()->json();
    }

    public function test_full_cycle_credits_wallet_only_after_ads(): void
    {
        $s = $this->start(1);
        $this->assertSame('ok', $s['status']);
        $this->assertTrue($s['earning']);

        $this->travel(5)->seconds();
        $this->assertSame(5, $this->beat($s['token'])['counted_seconds']);
        $this->travel(5)->seconds();
        $this->beat($s['token']);
        $this->travel(5)->seconds();
        $end = $this->end($s['token']);

        $this->assertTrue($end['counted']);
        $this->assertEqualsWithDelta(0.15, $end['earned'], 0.0001);
        $this->assertSame(1, $end['state']['ads_due']);
        $this->assertEqualsWithDelta(0, $end['state']['balance'], 0.0001, 'Nothing reaches the wallet before the cycle completes');

        // Next video is blocked until the ad is watched.
        $this->assertSame('ad_due', $this->start(2)['status']);

        // Claiming the ad too early is refused.
        $ad = $this->actingAs($this->user)->postJson(route('app.ad.start'))->json();
        $early = $this->actingAs($this->user)->postJson(route('app.ad.complete'), ['ad_view_id' => $ad['ad_view_id']])->json();
        $this->assertSame('too_early', $early['status']);

        $this->travel(15)->seconds();
        $done = $this->actingAs($this->user)->postJson(route('app.ad.complete'), ['ad_view_id' => $ad['ad_view_id']])->json();
        $this->assertSame('ok', $done['status']);
        $this->assertEqualsWithDelta(0.05, $done['earned'], 0.0001);
        $this->assertFalse($done['cycle_completed']);

        $s2 = $this->start(2);
        $this->assertSame('ok', $s2['status']);
        $this->travel(5)->seconds();
        $this->beat($s2['token']);
        $this->travel(5)->seconds();
        $this->end($s2['token']);

        $last = $this->watchAd();
        $this->assertTrue($last['cycle_completed']);
        $this->assertEqualsWithDelta(0.35, $last['credited'], 0.0001);
        $this->assertEqualsWithDelta(0.35, (float) $this->user->fresh()->balance, 0.0001);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $this->user->id, 'type' => 'credit', 'source' => 'cycle']);
    }

    public function test_heartbeat_cannot_add_more_than_real_time(): void
    {
        $s = $this->start(1);
        $this->travel(60)->seconds();
        $this->assertLessThanOrEqual(8, $this->beat($s['token'])['counted_seconds']);
    }

    public function test_paused_time_is_not_counted(): void
    {
        $s = $this->start(1);
        $this->travel(4)->seconds();
        $this->beat($s['token'], false);           // paused after 4s
        $this->travel(120)->seconds();
        $this->beat($s['token'], true);            // resumed
        $this->travel(3)->seconds();
        $this->assertSame(7, $this->beat($s['token'])['counted_seconds']);
    }

    public function test_short_watch_does_not_count(): void
    {
        $s = $this->start(1);
        $this->travel(3)->seconds();
        $end = $this->end($s['token']);
        $this->assertFalse($end['counted']);
        $this->assertSame(0, $end['state']['cycle']['videos_watched']);
    }

    public function test_video_earns_only_once(): void
    {
        $s = $this->start(1);
        $this->travel(6)->seconds();
        $this->end($s['token']);
        $this->watchAd();

        $again = $this->start(1);
        $this->assertFalse($again['earning']);
        $this->assertSame('already_earned', $again['reason']);
    }

    public function test_daily_cycle_limit(): void
    {
        $this->plan->update(['daily_cycle_limit' => 1]);
        Setting::put(['videos_per_cycle' => '1', 'ads_per_cycle' => '1']);

        $s = $this->start(1);
        $this->travel(6)->seconds();
        $this->end($s['token']);
        $this->assertTrue($this->watchAd()['cycle_completed']);

        $next = $this->start(2);
        $this->assertFalse($next['earning']);
        $this->assertSame('limit_reached', $next['reason']);
        $this->assertTrue($next['state']['limit_reached']);
    }

    public function test_withdrawal_debits_and_reject_refunds(): void
    {
        app(\App\Services\WalletService::class)->credit($this->user, 600, 'admin', null, 'Test credit');

        $this->actingAs($this->user)->post(route('wallet.withdraw'), [
            'amount' => 400, 'method' => 'JazzCash', 'account_title' => 'Test', 'account_number' => '03001234567',
        ])->assertSessionHasErrors('amount');   // below 500 minimum

        $this->actingAs($this->user)->post(route('wallet.withdraw'), [
            'amount' => 500, 'method' => 'JazzCash', 'account_title' => 'Test', 'account_number' => '03001234567',
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(100, (float) $this->user->fresh()->balance, 0.001);

        $admin = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $admin->role = 'admin';
        $admin->save();

        $withdrawal = $this->user->withdrawals()->first();
        $this->actingAs($admin)->post(route('admin.withdrawals.reject', $withdrawal))->assertRedirect();
        $this->assertEqualsWithDelta(600, (float) $this->user->fresh()->balance, 0.001);
        $this->assertSame('rejected', $withdrawal->fresh()->status);
    }

    public function test_register_gives_default_plan(): void
    {
        Setting::put(['require_invite_code' => '0']);
        $this->post(route('register'), [
            'name' => 'New Person', 'phone' => '+92 311 7654321', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('feed'));

        $user = User::query()->where('phone', '03117654321')->first();
        $this->assertNotNull($user);
        $this->assertSame('Free', $user->currentPlan()->name);
    }

    public function test_pages_render(): void
    {
        foreach (['feed', 'earnings', 'plans', 'wallet', 'profile'] as $route) {
            $this->actingAs($this->user)->get(route($route))->assertOk();
        }
        $this->actingAs($this->user)->get(route('admin.dashboard'))->assertForbidden();

        $admin = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $admin->role = 'admin';
        $admin->save();

        foreach (['admin.dashboard', 'admin.videos.index', 'admin.videos.create', 'admin.ads.index', 'admin.ads.create',
                     'admin.plans.index', 'admin.plans.create', 'admin.payments.index', 'admin.withdrawals.index',
                     'admin.users.index', 'admin.fraud.index', 'admin.revenue.index', 'admin.settings.edit'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
        $this->actingAs($admin)->get(route('admin.users.show', $this->user))->assertOk();
        $this->get(route('manifest'))->assertOk()->assertJsonPath('display', 'standalone');
    }

    public function test_admin_settings_save(): void
    {
        $admin = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $admin->role = 'admin';
        $admin->save();

        $values = Setting::allValues();
        $values['rate_per_ad'] = '0.08';
        $values['earn_once_per_video'] = '1';

        $this->actingAs($admin)->put(route('admin.settings.update'), $values)->assertSessionHasNoErrors();
        $this->assertSame('0.08', Setting::get('rate_per_ad'));
    }
}
