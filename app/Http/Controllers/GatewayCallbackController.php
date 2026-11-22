<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\GatewayPayments;
use App\Services\PkPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public webhook that PKPay calls on every payment status change.
 * The body is verified by HMAC signature before anything is trusted.
 */
class GatewayCallbackController extends Controller
{
    public function __construct(private PkPayService $pkpay, private GatewayPayments $gateway) {}

    public function __invoke(Request $request)
    {
        $raw = $request->getContent();
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if (! $this->pkpay->verifyCallback($timestamp, $raw, $signature)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = json_decode($raw, true);
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            return response()->json(['ok' => true]);   // nothing to do, acknowledge
        }

        $sandbox = $request->header('X-Sandbox') === 'true'
            || ! empty($payload['sandbox']) || ! empty($data['sandbox']);

        $payment = $this->findPayment($data);
        if (! $payment) {
            Log::info('PKPay callback for unknown payment', ['order_id' => $data['order_id'] ?? null, 'sandbox' => $sandbox]);

            return response()->json(['ok' => true]);
        }

        // The gateway is the source of truth: pull the authoritative amount/status
        // from the signed payload we just verified.
        $this->gateway->applyStatus($payment, $data, $sandbox, viaCallback: true);

        return response()->json(['ok' => true]);
    }

    private function findPayment(array $data): ?Payment
    {
        if (! empty($data['payment_id'])) {
            $p = Payment::query()->where('gateway_payment_id', $data['payment_id'])->first();
            if ($p) {
                return $p;
            }
        }
        if (! empty($data['order_id']) && preg_match('/^DRAMA-(\d+)$/', (string) $data['order_id'], $m)) {
            return Payment::query()->whereKey((int) $m[1])->where('gateway', 'pkpay')->first();
        }

        return null;
    }
}
