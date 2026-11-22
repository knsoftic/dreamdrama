<?php

namespace App\Http\Controllers\SubAdmin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class WithdrawalController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    public function index(Request $request)
    {
        $me = $request->user();

        return view('subadmin.withdrawals', [
            'available' => (float) $me->balance,
            'totalEarned' => (float) $me->total_earned,
            'totalWithdrawn' => (float) $me->total_withdrawn,
            'min' => (float) setting('subadmin_min_withdrawal', 500),
            'methods' => csv_setting('withdraw_methods'),
            'pending' => $me->withdrawals()->where('status', 'pending')->exists(),
            'withdrawals' => $me->withdrawals()->latest('id')->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $min = (float) setting('subadmin_min_withdrawal', 500);

        if (setting('withdrawals_enabled', '1') !== '1') {
            return back()->withErrors(['amount' => 'Withdrawals are currently closed.']);
        }
        if ($me->withdrawals()->where('status', 'pending')->exists()) {
            return back()->withErrors(['amount' => 'You already have a withdrawal pending.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$min, 'max:'.floor((float) $me->balance)],
            'method' => ['required', Rule::in(csv_setting('withdraw_methods'))],
            'account_title' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:60'],
        ], [
            'amount.min' => 'You can withdraw at least '.money($min, 0).'.',
            'amount.max' => 'You do not have that much available.',
        ]);

        try {
            DB::transaction(function () use ($me, $data) {
                $withdrawal = Withdrawal::query()->create([
                    'user_id' => $me->id,
                    'amount' => round((float) $data['amount'], 2),
                    'method' => $data['method'],
                    'account_title' => $data['account_title'],
                    'account_number' => $data['account_number'],
                ]);
                $this->wallet->debit($me, (float) $withdrawal->amount, 'withdrawal', $withdrawal, "Withdrawal #{$withdrawal->id} ({$data['method']})");
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Withdrawal request sent. The admin will process it.');
    }
}
