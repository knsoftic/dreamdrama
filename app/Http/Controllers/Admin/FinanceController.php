<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RevenueEntry;
use App\Models\Withdrawal;
use App\Services\GatewayPayments;
use App\Services\PkPayService;
use App\Services\SubscriptionService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Subscription payments, withdrawals and ad-network revenue entries. */
class FinanceController extends Controller
{
    public function __construct(
        private WalletService $wallet,
        private SubscriptionService $subscriptions,
        private PkPayService $pkpay,
        private GatewayPayments $gateway,
    ) {}

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

        $this->subscriptions->activateForPayment($payment, $request->input('admin_note') ?: 'Admin ne manually approve ki');

        return back()->with('success', 'Payment approved and the plan is now active.');
    }

    /** Re-check a gateway payment's real status (if a callback was missed). */
    public function checkPayment(Payment $payment)
    {
        abort_unless($payment->isGateway() && $payment->gateway_payment_id, 422);

        try {
            $data = $this->pkpay->getPayment($payment->gateway_payment_id);
            $this->gateway->applyStatus($payment, $data);
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        $status = $payment->fresh()->status;

        return back()->with('success', $status === 'approved'
            ? 'The payment was paid on the gateway — the plan is now active.'
            : 'Gateway status: '.($payment->fresh()->gateway_status ?? 'pending').'.');
    }

    public function rejectPayment(Request $request, Payment $payment)
    {
        abort_unless($payment->status === 'pending', 422);
        $payment->update([
            'status' => 'rejected',
            'admin_note' => $request->input('admin_note') ?: 'Payment could not be verified.',
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Payment rejected.');
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

        return back()->with('success', 'Withdrawal marked as paid.');
    }

    public function rejectWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        abort_unless($withdrawal->status === 'pending', 422);

        DB::transaction(function () use ($withdrawal, $request) {
            $withdrawal->update([
                'status' => 'rejected',
                'admin_note' => $request->input('admin_note') ?: 'Request rejected.',
                'processed_at' => now(),
            ]);
            $this->wallet->credit($withdrawal->user, (float) $withdrawal->amount, 'withdrawal_refund', $withdrawal, "Withdrawal #{$withdrawal->id} refund");
        });

        return back()->with('success', "Withdrawal rejected and the amount returned to the user's wallet.");
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

        return back()->with('success', 'Revenue entry added.');
    }

    public function destroyRevenue(RevenueEntry $entry)
    {
        $entry->delete();

        return back()->with('success', 'Entry deleted.');
    }
}
