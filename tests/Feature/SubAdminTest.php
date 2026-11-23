<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(['require_invite_code' => '1', 'max_accounts_per_ip' => '0']);
        Plan::query()->create([
            'name' => 'Free', 'slug' => 'free', 'price' => 0, 'duration_days' => 0, 'daily_cycle_limit' => 3,
            'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 500, 'is_default' => true, 'is_active' => true,
        ]);
    }

    private function subAdmin(string $code = 'BOSS123'): User
    {
        $u = User::query()->create(['name' => 'Boss', 'phone' => '0300'.random_int(1000000, 9999999), 'password' => 'secret123']);
        $u->forceFill(['role' => 'sub_admin', 'referral_code' => $code])->save();

        return $u;
    }

    private function admin(): User
    {
        $u = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    private function register(string $phone, ?string $ref): void
    {
        $this->post(route('logout'));   // ensure guest
        $this->post(route('register'), array_filter([
            'name' => 'U'.$phone, 'phone' => $phone, 'password' => 'secret123', 'password_confirmation' => 'secret123', 'ref' => $ref,
        ]));
    }

    public function test_invite_code_is_required(): void
    {
        $this->post(route('register'), ['name' => 'X', 'phone' => '03111111111', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasErrors('ref');

        Setting::put(['require_invite_code' => '0']);
        $this->post(route('logout'));
        $this->post(route('register'), ['name' => 'X', 'phone' => '03111111111', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasNoErrors();
    }

    public function test_network_ownership_propagates(): void
    {
        $boss = $this->subAdmin('BOSS123');

        $this->register('03111000001', 'BOSS123');   // A under boss
        $a = User::query()->where('phone', '03111000001')->first();
        $this->assertSame($boss->id, $a->sub_admin_id);
        $this->assertSame($boss->id, $a->referred_by);

        $this->register('03111000002', $a->referral_code);   // B invited by A
        $b = User::query()->where('phone', '03111000002')->first();
        $this->assertSame($boss->id, $b->sub_admin_id);   // still under boss
        $this->assertSame($a->id, $b->referred_by);

        // Downline levels
        $this->assertSame([1 => 1, 2 => 1, 3 => 0], $boss->downlineCounts());
        $this->assertSame([1 => 1, 2 => 0, 3 => 0], $a->fresh()->downlineCounts());
    }

    public function test_promote_moves_existing_downline(): void
    {
        // u invited c (both normal users, no sub-admin yet)
        $u = User::query()->create(['name' => 'U', 'phone' => '03111222333', 'password' => 'secret123']);
        $c = User::query()->create(['name' => 'C', 'phone' => '03111222334', 'password' => 'secret123', 'referred_by' => $u->id]);

        $this->actingAs($this->admin())->post(route('admin.subadmins.promote'), [
            'phone' => '03111222333', 'referral_code' => 'UBOSS', 'commission_percent' => 15,
        ])->assertRedirect(route('admin.subadmins.index'))->assertSessionHasNoErrors();

        $this->assertTrue($u->fresh()->isSubAdmin());
        $this->assertSame('UBOSS', $u->fresh()->referral_code);
        $this->assertSame($u->id, $c->fresh()->sub_admin_id);   // existing invitee moved under u
    }

    public function test_super_admin_creates_sub_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.subadmins.store'), [
            'name' => 'New Boss', 'phone' => '03115556677', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'referral_code' => 'NEWB22', 'commission_percent' => 20,
        ])->assertRedirect(route('admin.subadmins.index'))->assertSessionHasNoErrors();

        $sa = User::query()->where('referral_code', 'NEWB22')->first();
        $this->assertTrue($sa->isSubAdmin());

        // Duplicate code is rejected.
        $this->actingAs($admin)->post(route('admin.subadmins.store'), [
            'name' => 'Dup', 'phone' => '03115556688', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'referral_code' => 'NEWB22', 'commission_percent' => 20,
        ])->assertSessionHasErrors('referral_code');
    }

    public function test_panel_access_and_scoped_block(): void
    {
        $boss = $this->subAdmin('BOSS123');
        $this->register('03111000011', 'BOSS123');
        $mine = User::query()->where('phone', '03111000011')->first();

        $otherBoss = $this->subAdmin('OTHER99');
        $this->register('03111000012', 'OTHER99');
        $notMine = User::query()->where('phone', '03111000012')->first();

        // Sub-admin sees their panel; a normal user and the super-admin are scoped out.
        $this->actingAs($boss)->get(route('team.dashboard'))->assertOk();
        $this->actingAs($boss)->get(route('team.users.index'))->assertOk();
        $this->actingAs($mine)->get(route('team.dashboard'))->assertForbidden();
        $this->actingAs($boss)->get(route('admin.dashboard'))->assertForbidden();

        // Can block a user in the network, but not one outside it.
        $this->actingAs($boss)->post(route('team.users.block', $mine))->assertRedirect();
        $this->assertTrue($mine->fresh()->isBlocked());
        $this->actingAs($boss)->post(route('team.users.block', $notMine))->assertForbidden();
    }

    public function test_login_redirects_sub_admin_to_team(): void
    {
        $boss = $this->subAdmin('BOSS123');
        $boss->update(['password' => 'secret123']);
        $this->post(route('login'), ['login' => $boss->phone, 'password' => 'secret123'])
            ->assertRedirect(route('team.dashboard'));
    }

    public function test_super_admin_can_edit_a_sub_admin_name(): void
    {
        $boss = $this->subAdmin('BOSS123');
        $boss->update(['name' => 'Old Name']);
        $member = User::query()->create(['name' => 'M', 'phone' => '03119990001', 'password' => 'secret123', 'sub_admin_id' => $boss->id]);

        $this->actingAs($this->admin())->post(route('admin.subadmins.name', $boss), ['name' => 'New Name'])->assertRedirect();

        // One source of truth — every user under this sub-admin now reads the new name.
        $this->assertSame('New Name', $boss->fresh()->name);
        $this->assertSame('New Name', $member->fresh()->subAdmin->name);
    }
}
