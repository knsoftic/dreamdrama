<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Approve a payment and start the plan's subscription. Idempotent: a payment
     * that is already approved is left untouched. Used by both the manual admin
     * approval and the automatic gateway callback. When the buyer belongs to a
     * sub-admin, the sub-admin is credited their net share (deposit minus the
     * tax % snapshotted on the payment).
     */
    public function activateForPayment(Payment $payment, ?string $note = null): bool
    {
        return (bool) DB::transaction(function () use ($payment, $note) {
            $fresh = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status === 'approved') {
                return false;
            }

            $plan = $fresh->plan;

            Subscription::query()->where('user_id', $fresh->user_id)->where('status', 'active')
                ->update(['status' => 'cancelled']);

            Subscription::query()->create([
                'user_id' => $fresh->user_id,
                'plan_id' => $plan->id,
                'payment_id' => $fresh->id,
                'starts_at' => now(),
                'ends_at' => $plan->duration_days > 0 ? now()->addDays($plan->duration_days) : null,
                'status' => 'active',
            ]);

            $fresh->update([
                'status' => 'approved',
                'admin_note' => $note ?? $fresh->admin_note,
                'reviewed_at' => now(),
            ]);

            // Credit the owning sub-admin their net share of this deposit.
            if ($fresh->sub_admin_id) {
                $subAdmin = User::query()->whereKey($fresh->sub_admin_id)->where('role', 'sub_admin')->first();
                $net = $fresh->netAmount();
                if ($subAdmin && $net > 0) {
                    $this->wallet->credit($subAdmin, $net, 'commission', $fresh, "Deposit #{$fresh->id} commission");
                }
            }

            $payment->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }
}
