<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PkPayService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Configure the PKPay gateway. The API secret is stored server-side and never
 * rendered back to the browser.
 */
class PaymentGatewayController extends Controller
{
    public function __construct(private PkPayService $pkpay) {}

    public function index()
    {
        return view('admin.gateway', [
            'mode' => setting('payment_mode', 'manual'),
            'effectiveMode' => $this->pkpay->mode(),   // what users actually see right now
            'baseUrl' => setting('pkpay_base_url', 'https://pkpay.site/api/v1'),
            'merchantId' => setting('pkpay_merchant_id', ''),
            'hasKey' => setting('pkpay_api_key', '') !== '',
            'hasSecret' => setting('pkpay_api_secret', '') !== '',
            'configured' => $this->pkpay->configured(),
            'callbackUrl' => route('pkpay.callback'),
            'gatewayPayments' => Payment::query()->where('gateway', 'pkpay')->with(['user', 'plan'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'payment_mode' => ['required', Rule::in(['manual', 'gateway', 'both'])],
            'pkpay_base_url' => ['required', 'url', 'max:255'],
            'pkpay_merchant_id' => ['nullable', 'string', 'max:100'],
            'pkpay_api_key' => ['nullable', 'string', 'max:255'],
            'pkpay_api_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $values = [
            'payment_mode' => $data['payment_mode'],
            'pkpay_base_url' => rtrim($data['pkpay_base_url'], '/'),
            'pkpay_merchant_id' => $data['pkpay_merchant_id'] ?? '',
        ];
        // Only overwrite the key/secret when new values are entered.
        if (! empty($data['pkpay_api_key'])) {
            $values['pkpay_api_key'] = trim($data['pkpay_api_key']);
        }
        if (! empty($data['pkpay_api_secret'])) {
            $values['pkpay_api_secret'] = trim($data['pkpay_api_secret']);
        }

        Setting::put($values);

        $msg = 'Payment settings saved.';
        if ($data['payment_mode'] !== 'manual' && ! $this->pkpay->configured()) {
            $msg .= ' Note: add the API key and secret below, or users will still see the manual option.';
        }

        return back()->with('success', $msg);
    }

    public function clearSecret()
    {
        Setting::put(['pkpay_api_key' => '', 'pkpay_api_secret' => '', 'payment_mode' => 'manual']);

        return back()->with('success', 'Gateway credentials removed; plan purchase is back to manual.');
    }

    public function test()
    {
        try {
            $balance = $this->pkpay->balance();
        } catch (RuntimeException $e) {
            return back()->withErrors(['test' => $e->getMessage()]);
        }

        return back()->with('success', 'Gateway connected ✓ — available balance: Rs '.($balance['available'] ?? '0').'.');
    }
}
