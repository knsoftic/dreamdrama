<?php

namespace App\Http\Controllers;

use App\Models\AdUnit;
use App\Models\Cycle;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Withdrawal;
use App\Services\EarningService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserPagesController extends Controller
{
    public function __construct(private EarningService $earning, private WalletService $wallet) {}

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

        return view('plans.checkout', ['plan' => $plan, 'methods' => csv_setting('payment_methods')]);
    }

    public function pay(Request $request, Plan $plan)
    {
        abort_if(! $plan->is_active || $plan->isFree(), 404);
        $user = $request->user();

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
            'amount' => $plan->price,
            'method' => $data['method'],
            'transaction_id' => $data['transaction_id'],
            'sender_number' => $data['sender_number'] ?? null,
            'screenshot_path' => $request->file('screenshot')?->store('payments', 'public'),
        ]);

        return redirect()->route('plans')->with('success', 'Payment submit ho gayi. Admin verify kar ke plan active kar dega.');
    }

    public function wallet(Request $request)
    {
        $user = $request->user();

        return view('wallet.index', [
            'user' => $user,
            'plan' => $user->currentPlan(),
            'methods' => csv_setting('withdraw_methods'),
            'transactions' => $user->transactions()->latest('id')->paginate(15),
            'withdrawals' => $user->withdrawals()->latest('id')->limit(10)->get(),
            'pendingWithdrawal' => $user->withdrawals()->where('status', 'pending')->exists(),
            'banner' => AdUnit::pick('page_banner'),
        ]);
    }

    public function withdraw(Request $request)
    {
        $user = $request->user();
        $plan = $user->currentPlan();

        if (setting('withdrawals_enabled', '1') !== '1') {
            return back()->withErrors(['amount' => 'Withdrawals abhi band hain.']);
        }
        if ($user->withdrawals()->where('status', 'pending')->exists()) {
            return back()->withErrors(['amount' => 'Aap ki ek withdrawal request pehle se pending hai.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.(float) $plan->min_withdrawal, 'max:'.floor((float) $user->balance)],
            'method' => ['required', Rule::in(csv_setting('withdraw_methods'))],
            'account_title' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:60'],
        ], [
            'amount.min' => 'Kam az kam '.money($plan->min_withdrawal).' withdraw ho sakte hain.',
            'amount.max' => 'Aap ke wallet mein itna balance nahi hai.',
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
