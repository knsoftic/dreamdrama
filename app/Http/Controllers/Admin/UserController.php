<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EarningService;
use App\Services\WalletService;
use App\Services\WithdrawalRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserController extends Controller
{
    public function __construct(
        private WalletService $wallet,
        private EarningService $earning,
        private WithdrawalRules $rules,
    ) {}

    public function index(Request $request)
    {
        $filter = $request->query('filter');

        $users = User::query()
            ->where('role', 'user')
            ->when($request->query('q'), function ($q, $s) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('last_ip', $s));
            })
            ->when($filter === 'flagged', fn ($q) => $q->where('is_flagged', true))
            ->when($filter === 'blocked', fn ($q) => $q->where('status', 'blocked'))
            ->withCount('referrals')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'filter' => $filter]);
    }

    public function show(User $user)
    {
        return view('admin.users.show', [
            'user' => $user,
            'plan' => $user->currentPlan(),
            'subscription' => $user->activeSubscription(),
            'plans' => Plan::query()->orderBy('price')->get(),
            'state' => $this->earning->state($user),
            'withdrawRules' => $this->rules->check($user),
            'referralsTotal' => $user->referrals()->count(),
            'referralsActive' => $this->rules->activeReferrals($user),
            'referrer' => $user->referrer,
            'cycles' => $user->cycles()->latest('id')->limit(15)->get(),
            'transactions' => $user->transactions()->latest('id')->limit(20)->get(),
            'withdrawals' => $user->withdrawals()->latest('id')->limit(10)->get(),
            'sameIp' => User::query()->where('id', '!=', $user->id)
                ->where(fn ($q) => $q->where('register_ip', $user->register_ip)->orWhere('last_ip', $user->last_ip))
                ->limit(20)->get(),
            'fraudLogs' => FraudLog::query()->where('user_id', $user->id)->latest()->limit(10)->get(),
        ]);
    }

    public function toggleBlock(User $user)
    {
        abort_if($user->isAdmin(), 403);
        $user->update(['status' => $user->isBlocked() ? 'active' : 'blocked']);

        return back()->with('success', $user->isBlocked() ? 'User blocked.' : 'User unblocked.');
    }

    public function toggleFlag(User $user)
    {
        $user->is_flagged = ! $user->is_flagged;
        $user->save();

        return back()->with('success', $user->is_flagged ? 'User flagged.' : 'Flag removed.');
    }

    public function adjustBalance(Request $request, User $user)
    {
        $data = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:200'],
        ]);

        try {
            $method = $data['type'];
            $this->wallet->{$method}($user, (float) $data['amount'], 'admin', null, $data['description']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Balance updated.');
    }

    public function setPlan(Request $request, User $user)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'days' => ['nullable', 'integer', 'min:0'],
        ]);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        $days = isset($data['days']) ? (int) $data['days'] : (int) $plan->duration_days;

        DB::transaction(function () use ($user, $plan, $days) {
            Subscription::query()->where('user_id', $user->id)->where('status', 'active')->update(['status' => 'cancelled']);
            Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => $days > 0 ? now()->addDays($days) : null,
                'status' => 'active',
            ]);
        });

        return back()->with('success', "User's plan set to {$plan->name}.");
    }

    public function fraud()
    {
        return view('admin.fraud.index', [
            'logs' => FraudLog::query()->with('user')->latest('id')->paginate(30),
            'sharedIps' => User::query()->where('role', 'user')->whereNotNull('register_ip')
                ->selectRaw('register_ip, COUNT(*) as total')->groupBy('register_ip')
                ->having('total', '>', 1)->orderByDesc('total')->limit(20)->get(),
        ]);
    }
}
