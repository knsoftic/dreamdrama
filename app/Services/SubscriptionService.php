<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Approve a payment and start the plan's subscription. Idempotent: a payment
     * that is already approved is left untouched. Used by both the manual admin
     * approval and the automatic gateway callback.
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

            $payment->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }
}
