<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RevenueEntry;
use App\Models\Subscription;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Subscription payments, withdrawals and ad-network revenue entries. */
class FinanceController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    /* ---------------- Subscription payments ---------------- */

    public function payments(Request $request)
    {
        $status = $request->query('status', 'pending');

        return view('admin.payments.index', [
            'status' => $status,
            'payments' => Payment::query()->with(['user', 'plan'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function approvePayment(Request $request, Payment $payment)
    {
        abort_unless($payment->status === 'pending', 422);

        DB::transaction(function () use ($payment, $request) {
            $plan = $payment->plan;

            Subscription::query()->where('user_id', $payment->user_id)->where('status', 'active')
                ->update(['status' => 'cancelled']);

            Subscription::query()->create([
                'user_id' => $payment->user_id,
                'plan_id' => $plan->id,
                'payment_id' => $payment->id,
                'starts_at' => now(),
                'ends_at' => $plan->duration_days > 0 ? now()->addDays($plan->duration_days) : null,
                'status' => 'active',
            ]);

            $payment->update([
                'status' => 'approved',
                'admin_note' => $request->input('admin_note'),
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('success', 'Payment approve ho gayi aur plan active kar diya gaya.');
    }

    public function rejectPayment(Request $request, Payment $payment)
    {
        abort_unless($payment->status === 'pending', 422);
        $payment->update([
            'status' => 'rejected',
            'admin_note' => $request->input('admin_note') ?: 'Payment verify nahi hui.',
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Payment reject kar di gayi.');
    }

    /* ---------------- Withdrawals ---------------- */

    public function withdrawals(Request $request)
    {
        $status = $request->query('status', 'pending');

        return view('admin.withdrawals.index', [
            'status' => $status,
            'withdrawals' => Withdrawal::query()->with('user')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function payWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        abort_unless($withdrawal->status === 'pending', 422);

        DB::transaction(function () use ($withdrawal, $request) {
            $withdrawal->update([
                'status' => 'paid',
                'admin_note' => $request->input('admin_note'),
                'processed_at' => now(),
            ]);
            $withdrawal->user()->increment('total_withdrawn', $withdrawal->amount);
        });

        return back()->with('success', 'Withdrawal "Paid" mark ho gayi.');
    }

    public function rejectWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        abort_unless($withdrawal->status === 'pending', 422);

        DB::transaction(function () use ($withdrawal, $request) {
            $withdrawal->update([
                'status' => 'rejected',
                'admin_note' => $request->input('admin_note') ?: 'Request reject kar di gayi.',
                'processed_at' => now(),
            ]);
            $this->wallet->credit($withdrawal->user, (float) $withdrawal->amount, 'withdrawal_refund', $withdrawal, "Withdrawal #{$withdrawal->id} refund");
        });

        return back()->with('success', 'Withdrawal reject ho gayi aur paise user ke wallet mein wapas aa gaye.');
    }

    /* ---------------- Ad network revenue ---------------- */

    public function revenue()
    {
        return view('admin.revenue.index', [
            'entries' => RevenueEntry::query()->latest('date')->latest('id')->paginate(30),
        ]);
    }

    public function storeRevenue(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'network' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['impressions'] = (int) ($data['impressions'] ?? 0);
        RevenueEntry::query()->create($data);

        return back()->with('success', 'Revenue entry add ho gayi.');
    }

    public function destroyRevenue(RevenueEntry $entry)
    {
        $entry->delete();

        return back()->with('success', 'Entry delete ho gayi.');
    }
}
