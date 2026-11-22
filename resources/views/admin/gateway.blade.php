@extends('layouts.admin')
@section('title', 'Payment Gateway')

@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">

    {{-- Config --}}
    <form method="POST" action="{{ route('admin.gateway.save') }}" class="a-card space-y-4 p-5">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-slate-900">PKPay gateway</h2>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $configured ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $configured ? 'Credentials set' : 'Not set' }}
            </span>
        </div>

        <div>
            <label class="a-label">How users pay for a plan</label>
            <select name="payment_mode" class="a-input">
                <option value="manual" @selected($mode === 'manual')>Manual — user submits a TID, you approve</option>
                <option value="gateway" @selected($mode === 'gateway')>Automatic — online gateway (instant, auto-activated)</option>
                <option value="both" @selected($mode === 'both')>Both — the user chooses</option>
            </select>
            <p class="mt-1 text-xs text-slate-500">"Automatic" / "Both" need the API key & secret below. On an automatic payment the plan activates by itself once the gateway confirms it.</p>
        </div>

        {{-- What users actually see right now --}}
        <div class="rounded-lg border p-3 text-sm {{ $effectiveMode === 'manual' ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            <strong>Right now users see:</strong>
            {{ ['manual' => 'Manual payment only', 'gateway' => 'Automatic gateway only', 'both' => 'Both (automatic + manual)'][$effectiveMode] }}
            @if ($mode !== 'manual' && ! $configured)
                <div class="mt-1 text-xs text-rose-600">You chose an automatic mode but no API key/secret is saved yet — that's why manual is showing. Add them below and Save.</div>
            @endif
        </div>

        <div>
            <label class="a-label">API base URL</label>
            <input name="pkpay_base_url" value="{{ old('pkpay_base_url', $baseUrl) }}" class="a-input" required>
            @error('pkpay_base_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="a-label">Merchant ID <span class="text-slate-400">(optional, reference)</span></label>
            <input name="pkpay_merchant_id" value="{{ old('pkpay_merchant_id', $merchantId) }}" class="a-input">
        </div>
        <div>
            <label class="a-label">API key (pk_…)</label>
            <input name="pkpay_api_key" class="a-input font-mono" autocomplete="off" placeholder="{{ $hasKey ? '•••••••• (saved — leave empty to keep it)' : 'pk_...' }}">
            @error('pkpay_api_key')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="a-label">API secret (sk_…)</label>
            <input name="pkpay_api_secret" type="password" class="a-input font-mono" autocomplete="off" placeholder="{{ $hasSecret ? '•••••••• (saved)' : 'sk_...' }}">
            <p class="mt-1 text-xs text-slate-500">The secret is never shown. Enter a new value to change it.</p>
            @error('pkpay_api_secret')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-2">
            <button class="a-btn-primary">Save</button>
            @if ($hasSecret)
                <button formaction="{{ route('admin.gateway.clear') }}" class="a-btn-light text-rose-600"
                        onclick="return confirm('Remove credentials and turn the gateway off?')">Clear credentials</button>
            @endif
        </div>
    </form>

    {{-- Setup help + test --}}
    <div class="space-y-6">
        <div class="a-card p-5">
            <h2 class="font-bold text-slate-900">Required setup</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-600">
                <li>Set <strong>"How users pay"</strong> above to <em>Automatic</em> or <em>Both</em>.</li>
                <li>Get your <strong>API key</strong> and <strong>API secret</strong> from the PKPay merchant portal and enter them above.</li>
                <li><strong>IP allowlist:</strong> Give your server IP to the PKPay admin (Pay-In side), otherwise you get an <code>ip_not_allowed</code> error.</li>
                <li>Give the callback URL to PKPay (or it is sent automatically with each create-payment):
                    <div class="mt-1 flex items-center gap-2">
                        <code class="block flex-1 truncate rounded bg-slate-100 px-2 py-1 text-xs">{{ $callbackUrl }}</code>
                        <button type="button" class="a-btn-light !px-2 !py-1 text-xs" onclick="navigator.clipboard.writeText('{{ $callbackUrl }}')">Copy</button>
                    </div>
                </li>
                <li>After clicking "Save" above, verify with <strong>Test connection</strong> below.</li>
            </ol>
            <form method="POST" action="{{ route('admin.gateway.test') }}" class="mt-4">
                @csrf
                <button class="a-btn-light" {{ $configured ? '' : 'disabled' }}><x-icon name="bolt" class="size-4"/> Test connection</button>
            </form>
            @error('test')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="a-card p-5 text-sm text-slate-600">
            <h3 class="font-bold text-slate-900">Security</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>The secret stays on the server and is never sent to the browser.</li>
                <li>Every callback is verified by HMAC signature; sandbox callbacks never activate a plan.</li>
                <li>The plan activates only when the amount matches.</li>
                <li>If a callback is missed, "Check status" on the Payments page re-verifies it.</li>
            </ul>
        </div>
    </div>

    {{-- Recent gateway payments --}}
    <div class="a-card overflow-x-auto lg:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Online (gateway) payments</div>
        <table class="a-table">
            <thead><tr><th>User</th><th>Plan</th><th>Amount</th><th>Gateway status</th><th>Record</th><th>Waqt</th></tr></thead>
            <tbody>
            @forelse ($gatewayPayments as $p)
                <tr>
                    <td><a href="{{ route('admin.users.show', $p->user) }}" class="font-semibold text-violet-600">{{ $p->user->name }}</a></td>
                    <td>{{ $p->plan->name }}</td>
                    <td>{{ money($p->amount) }}</td>
                    <td>{{ $p->gateway_status ?? '—' }}</td>
                    <td>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ['approved' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'rejected' => 'bg-rose-100 text-rose-700'][$p->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($p->status) }}</span>
                    </td>
                    <td class="text-xs">{{ $p->created_at->format('d M, h:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">No online payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
