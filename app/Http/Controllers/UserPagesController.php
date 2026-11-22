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
     * Serve the APK from our own domain. The file is cached on our server
     * (refreshed hourly) and sent with a proper Content-Length, so the
     * download never reveals where the file is actually hosted and never
     * truncates on large files.
     */
    public function appFile()
    {
        abort_unless(setting('app_download_enabled', '1') === '1', 404);
        $source = (string) config('platform.apk_source');
        abort_if($source === '', 404);

        $path = storage_path('app/app-latest.apk');
        $fresh = is_file($path) && filesize($path) > 100000 && (time() - filemtime($path) < 3600);

        if (! $fresh) {
            @ignore_user_abort(true);   // a client that cancels must not leave a half-written cache
            try {
                $tmp = $path.'.tmp';
                $res = \Illuminate\Support\Facades\Http::timeout(300)->withOptions(['sink' => $tmp])->get($source);
                $expected = (int) $res->header('Content-Length');
                $got = is_file($tmp) ? filesize($tmp) : 0;
                // Only cache a complete file (size matches, or at least looks like a real APK).
                if ($res->successful() && $got > 100000 && ($expected === 0 || $got === $expected)) {
                    @rename($tmp, $path);
                } else {
                    @unlink($tmp);
                }
            } catch (\Throwable $e) {
                Log::warning('APK fetch failed', ['error' => $e->getMessage()]);
                @unlink($path.'.tmp');
            }
        }

        abort_unless(is_file($path), 404);   // keep serving the last good copy if a refresh fails

        $filename = \Illuminate\Support\Str::slug(setting('site_name', 'app')).'.apk';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Serves the admin-uploaded google-services.json so the APK build can fetch
     * it. This file is embedded in every APK anyway, so it is not a secret.
     */
    public function googleServices()
    {
        $json = (string) setting('google_services_json', '');
        abort_if($json === '', 404);

        return response($json, 200, ['Content-Type' => 'application/json']);
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
            'levels' => $user->downlineCounts(3),
            'referrals' => $user->referrals()
                ->withCount(['cycles as completed_cycles' => fn ($q) => $q->where('status', 'completed')])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function home(Request $request)
    {
        // Already logged in → straight into the app (feed), on web or in the app.
        if ($request->user()) {
            return redirect()->to(\App\Http\Controllers\Auth\AuthController::homeFor($request->user()));
        }

        // Inside the Android app, skip the marketing landing page and go to login.
        if (is_app_request()) {
            return redirect()->route('login');
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

        return view('plans.checkout', [
            'plan' => $plan,
            'methods' => csv_setting('payment_methods'),
            'gatewayOn' => $this->pkpay->showsGateway(),
            'manualOn' => $this->pkpay->showsManual(),
        ]);
    }

    /** Attach the owning sub-admin and a snapshot of their tax % to a deposit. */
    private function networkSnapshot($user): array
    {
        $tax = $user->sub_admin_id
            ? (float) (\App\Models\User::query()->whereKey($user->sub_admin_id)->value('commission_percent') ?? 0)
            : 0;

        return ['sub_admin_id' => $user->sub_admin_id, 'tax_percent' => $tax];
    }

    /** Expired, abandoned gateway payments shouldn't block a new purchase. */
    private function expireStalePayments($user): void
    {
        $user->payments()
            ->where('status', 'pending')
            ->where('gateway', 'pkpay')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'rejected', 'gateway_status' => 'expired', 'reviewed_at' => now(), 'admin_note' => 'QR expired']);
    }

    /** Manual payment: user submits a transaction id for the admin to verify. */
    public function pay(Request $request, Plan $plan)
    {
        abort_if(! $plan->is_active || $plan->isFree(), 404);
        abort_unless($this->pkpay->showsManual(), 404);
        $user = $request->user();
        $this->expireStalePayments($user);

        if ($user->payments()->where('status', 'pending')->exists()) {
            return redirect()->route('plans')->withErrors(['payment' => 'Your previous payment is still under review.']);
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
        ] + $this->networkSnapshot($user));

        return redirect()->route('plans')->with('success', 'Payment submitted. The admin will verify it and activate your plan.');
    }

    /** Gateway payment: create a PKPay order and send the user to its QR checkout. */
    public function payGateway(Request $request, Plan $plan)
    {
        abort_if(! $plan->is_active || $plan->isFree(), 404);
        abort_unless($this->pkpay->showsGateway(), 404);
        $user = $request->user();
        $this->expireStalePayments($user);

        if ($user->payments()->where('status', 'pending')->exists()) {
            return redirect()->route('plans')->withErrors(['payment' => 'Your previous payment is still under review.']);
        }

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'pkpay',
            'amount' => $plan->price,
            'method' => 'PKPay (Raast QR)',
            'gateway_status' => 'creating',
        ] + $this->networkSnapshot($user));

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
            return redirect()->route('plans.checkout', $plan)->withErrors(['payment' => 'The gateway did not return a checkout link.']);
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
            'amount.min' => 'You can withdraw at least '.money($rules['min'], 0).'.',
            'amount.max' => $planLimited
                ? 'Your plan allows at most '.money($rules['plan_max'], 0).' per withdrawal.'
                : 'You do not have enough balance in your wallet.',
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

        return back()->with('success', 'Withdrawal request sent. The admin will approve it and send the payment.');
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

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Password changed.');
    }
}
