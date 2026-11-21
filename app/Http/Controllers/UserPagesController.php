<?php

namespace App\Http\Controllers;

use App\Models\AdUnit;
use App\Models\Cycle;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Withdrawal;
use App\Services\EarningService;
use App\Services\GatewayPayments;
use App\Services\PkPayService;
use App\Services\WalletService;
use App\Services\WithdrawalRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class UserPagesController extends Controller
{
    public function __construct(
        private EarningService $earning,
        private WalletService $wallet,
        private WithdrawalRules $rules,
        private PkPayService $pkpay,
        private GatewayPayments $gateway,
    ) {}

    /** Invite link /r/CODE — remembers the code and opens the register page. */
    public function referralLink(Request $request, string $code)
    {
        if ($request->user()) {
            return redirect()->route('feed');
        }

        $request->session()->put('ref', strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $code), 0, 12)));

        return redirect()->route('register');
    }

    public function download()
    {
        abort_unless(setting('app_download_enabled', '1') === '1', 404);

        return view('download.index', [
            'available' => config('platform.apk_source') !== '',
            'version' => setting('app_version'),
            'size' => setting('app_size_mb'),
            'minAndroid' => setting('app_min_android'),
        ]);
    }

    /**
     * Serve the APK from our own domain by streaming it from the private source
     * URL, so the download never reveals where the file is actually hosted.
     */
    public function appFile()
    {
        abort_unless(setting('app_download_enabled', '1') === '1', 404);
        $source = (string) config('platform.apk_source');
        abort_if($source === '', 404);

        $filename = \Illuminate\Support\Str::slug(setting('site_name', 'app')).'.apk';

        return response()->streamDownload(function () use ($source) {
            @set_time_limit(0);
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            $ch = curl_init($source);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,   // follow GitHub's redirect to its CDN, server-side
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_HTTPHEADER => ['Accept: application/octet-stream'],
                CURLOPT_WRITEFUNCTION => function ($ch, $data) {
                    echo $data;
                    flush();

                    return strlen($data);
                },
            ]);
            curl_exec($ch);
            curl_close($ch);
        }, $filename, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function invite(Request $request)
    {
        $user = $request->user();
        $minCycles = (int) setting('referral_min_cycles', 1);

        return view('invite.index', [
            'user' => $user,
            'plan' => $user->currentPlan(),
            'minCycles' => $minCycles,
            'active' => $this->rules->activeReferrals($user),
            'total' => $user->referrals()->count(),
            'referrals' => $user->referrals()
                ->withCount(['cycles as completed_cycles' => fn ($q) => $q->where('status', 'completed')])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function home(Request $request)
    {
        if ($request->user()) {
            return redirect()->route($request->user()->isAdmin() ? 'admin.dashboard' : 'feed');
        }

        return view('welcome', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }

    public function feed(Request $request)
    {
        return view('feed.index', [
            'state' => $this->earning->state($request->user()),
            'banner' => AdUnit::pick('page_banner'),
        ]);
    }

    public function earnings(Request $request)
    {
        $user = $request->user();

        $week = Cycle::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', today()->subDays(6))
            ->selectRaw('DATE(completed_at) as d, SUM(credited_amount) as total, COUNT(*) as cycles')
            ->groupBy('d')
            ->pluck('total', 'd');

        $days = collect(range(6, 0))->map(function ($i) use ($week) {
            $date = today()->subDays($i);

            return ['label' => $date->format('D'), 'date' => $date->toDateString(), 'total' => (float) ($week[$date->toDateString()] ?? 0)];
        });

        return view('earnings.index', [
            'state' => $this->earning->state($user),
            'days' => $days,
            'cycles' => Cycle::query()->where('user_id', $user->id)->latest('id')->paginate(15),
            'banner' => AdUnit::pick('page_banner'),
        ]);
    }

    public function plans(Request $request)
    {
        $user = $request->user();

        return view('plans.index', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('price')->get(),
            'current' => $user->currentPlan(),
            'subscription' => $user->activeSubscription(),
            'pending' => $user->payments()->with('plan')->where('status', 'pending')->latest()->first(),
            'payments' => $user->payments()->with('plan')->latest()->limit(10)->get(),
        ]);
    }

    public function checkout(Request $request, Plan $plan)
    {
        abort_if(! $plan->is_active || $plan->isFree(), 404);
        $mode = setting('payment_mode', 'manual');
        $gatewayOn = $this->pkpay->enabled() && $mode !== 'manual';

        return view('plans.checkout', [
            'plan' => $plan,
            'methods' => csv_setting('payment_methods'),
            'gatewayOn' => $gatewayOn,
            'manualOn' => $mode !== 'gateway' || ! $this->pkpay->enabled(),
        ]);
    }

    /** Expired, abandoned gateway payments shouldn't block a new purchase. */
    private function expireStalePayments($user): void
    {
        $user->payments()
            ->where('status', 'pending')
            ->where('gateway', 'pkpay')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'rejected', 'gateway_status' => 'expired', 'reviewed_at' => now(), 'admin_note' => 'QR expire ho gaya']);
    }

    /** Manual payment: user submits a transaction id for the admin to verify. */
    public function pay(Request $request, Plan $plan)
    {
        abort_if(! $plan->is_active || $plan->isFree(), 404);
        abort_if(setting('payment_mode', 'manual') === 'gateway' && $this->pkpay->enabled(), 404);
        $user = $request->user();
        $this->expireStalePayments($user);

        if ($user->payments()->where('status', 'pending')->exists()) {
            return redirect()->route('plans')->withErrors(['payment' => 'Aap ki pichli payment abhi review mein hai.']);
        }

        $data = $request->validate([
            'method' => ['required', Rule::in(csv_setting('payment_methods'))],
            'transaction_id' => ['required', 'string', 'max:100'],
            'sender_number' => ['nullable', 'string', 'max:40'],
            'screenshot' => ['nullable', 'image', 'max:4096'],
        ]);

        Payment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'manual',
            'amount' => $plan->price,
            'method' => $data['method'],
            'transaction_id' => $data['transaction_id'],
            'sender_number' => $data['sender_number'] ?? null,
            'screenshot_path' => $request->file('screenshot')?->store('payments', 'public'),
        ]);

        return redirect()->route('plans')->with('success', 'Payment submit ho gayi. Admin verify kar ke plan active kar dega.');
    }

    /** Gateway payment: create a PKPay order and send the user to its QR checkout. */
    public function payGateway(Request $request, Plan $plan)
    {
        abort_if(! $plan->is_active || $plan->isFree(), 404);
        abort_unless($this->pkpay->enabled() && setting('payment_mode', 'manual') !== 'manual', 404);
        $user = $request->user();
        $this->expireStalePayments($user);

        if ($user->payments()->where('status', 'pending')->exists()) {
            return redirect()->route('plans')->withErrors(['payment' => 'Aap ki pichli payment abhi review mein hai.']);
        }

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'pkpay',
            'amount' => $plan->price,
            'method' => 'PKPay (Raast QR)',
            'gateway_status' => 'creating',
        ]);

        try {
            $data = $this->pkpay->createPayment([
                'amount' => round((float) $plan->price, 2),
                'order_id' => 'DRAMA-'.$payment->id,
                'description' => $plan->name.' plan — '.setting('site_name'),
                'customer' => array_filter([
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ]),
                'callback_url' => route('pkpay.callback'),
                'return_url' => route('payments.return', $payment),
                'metadata' => ['payment_id' => $payment->id, 'user_id' => $user->id],
            ], 'drama-'.$payment->id);
        } catch (RuntimeException $e) {
            $payment->delete();

            return redirect()->route('plans.checkout', $plan)->withErrors(['payment' => $e->getMessage()]);
        }

        $payment->update([
            'gateway_payment_id' => $data['payment_id'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'gateway_status' => $data['status'] ?? 'pending',
            'checkout_url' => $data['checkout_url'] ?? null,
            'expires_at' => isset($data['expires_at']) ? \Illuminate\Support\Carbon::parse($data['expires_at']) : null,
        ]);

        $url = $data['checkout_url'] ?? null;
        if (! $url) {
            return redirect()->route('plans.checkout', $plan)->withErrors(['payment' => 'Gateway ne checkout link nahi diya.']);
        }

        return redirect()->away($url);
    }

    /** Where the gateway returns the user after paying — confirm status and show the result. */
    public function paymentReturn(Request $request, Payment $payment)
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        if ($payment->status !== 'approved' && $payment->isGateway() && $payment->gateway_payment_id) {
            try {
                $data = $this->pkpay->getPayment($payment->gateway_payment_id);
                $this->gateway->applyStatus($payment, $data);
            } catch (RuntimeException $e) {
                Log::warning('PKPay status check failed', ['payment' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        return view('plans.return', [
            'payment' => $payment->fresh('plan'),
        ]);
    }

    public function wallet(Request $request)
    {
        $user = $request->user();

        return view('wallet.index', [
            'user' => $user,
            'plan' => $user->currentPlan(),
            'rules' => $this->rules->check($user),
            'methods' => csv_setting('withdraw_methods'),
            'transactions' => $user->transactions()->latest('id')->paginate(15),
            'withdrawals' => $user->withdrawals()->latest('id')->limit(10)->get(),
            'banner' => AdUnit::pick('page_banner'),
        ]);
    }

    public function withdraw(Request $request)
    {
        $user = $request->user();
        $rules = $this->rules->check($user);

        if (! $rules['allowed']) {
            $failed = collect($rules['checks'])->firstWhere('ok', false);

            return back()->withErrors(['amount' => $failed['label'].($failed['detail'] ? ' — '.$failed['detail'] : '')]);
        }

        $planLimited = $rules['plan_max'] > 0 && $rules['plan_max'] <= floor((float) $user->balance);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$rules['min'], 'max:'.$rules['max']],
            'method' => ['required', Rule::in(csv_setting('withdraw_methods'))],
            'account_title' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:60'],
        ], [
            'amount.min' => 'Kam az kam '.money($rules['min'], 0).' withdraw ho sakte hain.',
            'amount.max' => $planLimited
                ? 'Aap ke plan mein ek dafa mein zyada se zyada '.money($rules['plan_max'], 0).' withdraw ho sakte hain.'
                : 'Aap ke wallet mein itna balance nahi hai.',
        ]);

        DB::transaction(function () use ($user, $data) {
            $withdrawal = Withdrawal::query()->create([
                'user_id' => $user->id,
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'],
                'account_title' => $data['account_title'],
                'account_number' => $data['account_number'],
            ]);
            $this->wallet->debit($user, (float) $withdrawal->amount, 'withdrawal', $withdrawal, "Withdrawal #{$withdrawal->id} ({$data['method']})");
        });

        return back()->with('success', 'Withdrawal request bhej di gayi. Admin approve kar ke payment bhej dega.');
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        return view('profile.index', [
            'user' => $user,
            'plan' => $user->currentPlan(),
            'subscription' => $user->activeSubscription(),
            'state' => $this->earning->state($user),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $user->update(['name' => $data['name'], 'email' => isset($data['email']) ? strtolower($data['email']) : null]);

        return back()->with('success', 'Profile update ho gaya.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Purana password ghalat hai.']);
        }

        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Password change ho gaya.');
    }
}
