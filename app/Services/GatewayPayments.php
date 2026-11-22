<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Applies a gateway status object to a Payment and activates the plan when
 * the payment is genuinely paid. Shared by the user return page and the
 * webhook, so the rules live in one place.
 */
class GatewayPayments
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function applyStatus(Payment $payment, array $data, bool $sandbox = false): void
    {
        $status = $data['status'] ?? null;

        $payment->forceFill([
            'gateway_status' => $status,
            'gateway_meta' => collect($data)->only([
                'payment_id', 'transaction_id', 'order_id', 'amount', 'status', 'paid_at', 'expires_at',
            ])->all(),
            'paid_at' => ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : $payment->paid_at,
        ]);
        if (! empty($data['transaction_id'])) {
            $payment->transaction_id = $data['transaction_id'];
        }
        $payment->save();

        if ($sandbox) {
            return;   // sandbox callbacks never fulfil an order
        }

        $amountMatches = isset($data['amount']) && abs((float) $data['amount'] - (float) $payment->amount) < 0.01;

        if ($status === 'paid' && $amountMatches) {
            $this->subscriptions->activateForPayment($payment, 'Verified on PKPay');
        } elseif (in_array($status, ['expired', 'failed', 'cancelled'], true) && $payment->status === 'pending') {
            $payment->update(['status' => 'rejected', 'reviewed_at' => now(), 'admin_note' => 'Gateway: '.$status]);
        }
    }
}
