<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'sk_test_secret_value';

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::query()->create([
            'name' => 'Gold', 'slug' => 'gold', 'price' => 1500, 'duration_days' => 30, 'daily_cycle_limit' => 10,
            'rate_multiplier' => 2, 'daily_earning_cap' => 0, 'min_withdrawal' => 200, 'is_default' => false, 'is_active' => true,
        ]);
        Plan::query()->create([
            'name' => 'Free', 'slug' => 'free', 'price' => 0, 'duration_days' => 0, 'daily_cycle_limit' => 3,
            'rate_multiplier' => 1, 'daily_earning_cap' => 0, 'min_withdrawal' => 500, 'is_default' => true, 'is_active' => true,
        ]);

        Setting::put([
            'pkpay_enabled' => '1',
            'payment_mode' => 'both',
            'pkpay_base_url' => 'https://pkpay.site/api/v1',
            'pkpay_api_key' => 'pk_test_key',
            'pkpay_api_secret' => $this->secret,
        ]);
    }

    private function user(): User
    {
        $u = User::query()->create(['name' => 'Buyer', 'phone' => '0300'.random_int(1000000, 9999999), 'password' => 'secret123']);
        Subscription::query()->create(['user_id' => $u->id, 'plan_id' => Plan::defaultPlan()->id, 'starts_at' => now(), 'status' => 'active']);

        return $u;
    }

    public function test_checkout_shows_gateway_when_enabled(): void
    {
        $this->actingAs($this->user())->get(route('plans.checkout', $this->plan))
            ->assertOk()->assertSee('Online payment')->assertSee('Manual payment');

        Setting::put(['payment_mode' => 'manual']);
        $this->actingAs($this->user())->get(route('plans.checkout', $this->plan))
            ->assertOk()->assertDontSee('Online payment');
    }

    public function test_gateway_mode_without_credentials_falls_back_to_manual(): void
    {
        Setting::put(['payment_mode' => 'gateway', 'pkpay_api_key' => '', 'pkpay_api_secret' => '']);

        $this->actingAs($this->user())->get(route('plans.checkout', $this->plan))
            ->assertOk()->assertSee('Manual payment')->assertDontSee('Online payment');

        // And the gateway purchase route is closed while it isn't usable.
        $this->actingAs($this->user())->post(route('plans.pay-gateway', $this->plan))->assertNotFound();
    }

    public function test_pay_gateway_creates_order_and_redirects(): void
    {
        Http::fake([
            '*/api/v1/payments' => Http::response(['data' => [
                'payment_id' => 'pay_abc123', 'transaction_id' => 'TXN999', 'status' => 'pending',
                'amount' => '1500.00', 'checkout_url' => 'https://pkpay.site/checkout/pay_abc123',
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
            ]], 201),
        ]);

        $user = $this->user();
        $this->actingAs($user)->post(route('plans.pay-gateway', $this->plan))
            ->assertRedirect('https://pkpay.site/checkout/pay_abc123');

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id, 'gateway' => 'pkpay', 'gateway_payment_id' => 'pay_abc123', 'status' => 'pending',
        ]);

        // The request to the gateway was signed.
        Http::assertSent(fn ($req) => $req->hasHeader('X-Signature') && $req->hasHeader('X-Api-Key')
            && $req->hasHeader('Idempotency-Key'));
    }

    public function test_pay_gateway_shows_ip_error_gracefully(): void
    {
        Http::fake(['*/api/v1/payments' => Http::response(['error' => ['code' => 'ip_not_allowed', 'message' => 'no']], 403)]);

        $user = $this->user();
        $this->actingAs($user)->post(route('plans.pay-gateway', $this->plan))
            ->assertRedirect(route('plans.checkout', $this->plan))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Payment::query()->count());   // failed order cleaned up
    }

    private function postCallback(array $payload, ?string $secret = null, bool $sandbox = false)
    {
        $raw = json_encode($payload);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts.'.'.$raw, $secret ?? $this->secret);
        $server = [
            'HTTP_X_TIMESTAMP' => $ts,
            'HTTP_X_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ];
        if ($sandbox) {
            $server['HTTP_X_SANDBOX'] = 'true';
        }

        return $this->call('POST', route('pkpay.callback'), [], [], [], $server, $raw);
    }

    private function pendingGatewayPayment(User $user): Payment
    {
        return Payment::query()->create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'gateway' => 'pkpay',
            'gateway_payment_id' => 'pay_abc123', 'amount' => 1500, 'method' => 'PKPay', 'status' => 'pending',
            'gateway_status' => 'pending',
        ]);
    }

    public function test_paid_callback_activates_plan(): void
    {
        $user = $this->user();
        $payment = $this->pendingGatewayPayment($user);

        $this->postCallback(['event' => 'payment.paid', 'data' => [
            'payment_id' => 'pay_abc123', 'order_id' => 'DRAMA-'.$payment->id, 'status' => 'paid',
            'amount' => '1500.00', 'transaction_id' => 'TXN999', 'paid_at' => now()->toIso8601String(),
        ]])->assertOk();

        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame($this->plan->id, $user->fresh()->currentPlan()->id);
    }

    public function test_callback_rejects_bad_signature(): void
    {
        $user = $this->user();
        $payment = $this->pendingGatewayPayment($user);

        $this->postCallback(['data' => ['payment_id' => 'pay_abc123', 'status' => 'paid', 'amount' => '1500.00']], 'wrong_secret')
            ->assertStatus(401);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_sandbox_callback_does_not_activate(): void
    {
        $user = $this->user();
        $payment = $this->pendingGatewayPayment($user);

        $this->postCallback(['data' => [
            'payment_id' => 'pay_abc123', 'status' => 'paid', 'amount' => '1500.00',
        ]], null, true)->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);   // sandbox never fulfils
    }

    public function test_callback_amount_mismatch_does_not_activate(): void
    {
        $user = $this->user();
        $payment = $this->pendingGatewayPayment($user);

        $this->postCallback(['data' => [
            'payment_id' => 'pay_abc123', 'status' => 'paid', 'amount' => '50.00',
        ]])->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_admin_check_status_activates(): void
    {
        $user = $this->user();
        $payment = $this->pendingGatewayPayment($user);

        Http::fake(['*/api/v1/payments/*' => Http::response(['data' => [
            'payment_id' => 'pay_abc123', 'status' => 'paid', 'amount' => '1500.00', 'transaction_id' => 'TXN999',
            'paid_at' => now()->toIso8601String(),
        ]], 200)]);

        $admin = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->post(route('admin.payments.check', $payment))->assertRedirect();
        $this->assertSame('approved', $payment->fresh()->status);
    }

    public function test_manual_approval_still_works(): void
    {
        Setting::put(['payment_mode' => 'manual']);
        $user = $this->user();
        $payment = Payment::query()->create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'gateway' => 'manual',
            'amount' => 1500, 'method' => 'JazzCash', 'transaction_id' => 'ABC', 'status' => 'pending',
        ]);

        $admin = User::query()->create(['name' => 'Admin', 'phone' => '03000000001', 'password' => 'secret123']);
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->post(route('admin.payments.approve', $payment))->assertRedirect();
        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame($this->plan->id, $user->fresh()->currentPlan()->id);
    }
}
