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
            <label class="a-label">Plan purchase ka tareeqa</label>
            <select name="payment_mode" class="a-input">
                <option value="manual" @selected($mode === 'manual')>Sirf manual (TID + admin approve)</option>
                <option value="gateway" @selected($mode === 'gateway')>Sirf online gateway (PKPay)</option>
                <option value="both" @selected($mode === 'both')>Dono (user chun le)</option>
            </select>
            <p class="mt-1 text-xs text-slate-500">Gateway chalane ke liye neeche credentials bhi zaroori hain aur "Gateway on" hona chahiye.</p>
        </div>

        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="pkpay_enabled" value="0">
            <input type="checkbox" name="pkpay_enabled" value="1" class="size-4 accent-violet-600" @checked($enabled)> Gateway on
        </label>

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
            <input name="pkpay_api_key" class="a-input font-mono" autocomplete="off" placeholder="{{ $hasKey ? '•••••••• (saved — khali chhorein to wahi rahegi)' : 'pk_...' }}">
            @error('pkpay_api_key')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="a-label">API secret (sk_…)</label>
            <input name="pkpay_api_secret" type="password" class="a-input font-mono" autocomplete="off" placeholder="{{ $hasSecret ? '•••••••• (saved)' : 'sk_...' }}">
            <p class="mt-1 text-xs text-slate-500">Secret kabhi dikhaya nahi jata. Badalna ho to nayi value likhein.</p>
            @error('pkpay_api_secret')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap gap-2">
            <button class="a-btn-primary">Save</button>
            @if ($hasSecret)
                <button formaction="{{ route('admin.gateway.clear') }}" class="a-btn-light text-rose-600"
                        onclick="return confirm('Credentials hata kar gateway band karein?')">Clear credentials</button>
            @endif
        </div>
    </form>

    {{-- Setup help + test --}}
    <div class="space-y-6">
        <div class="a-card p-5">
            <h2 class="font-bold text-slate-900">Zaroori setup</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-600">
                <li>PKPay merchant portal se <strong>API key</strong> aur <strong>API secret</strong> le kar upar daalein.</li>
                <li><strong>IP allowlist:</strong> PKPay admin ko apne server ka IP dein (Pay-In side), warna <code>ip_not_allowed</code> error aayega.</li>
                <li>Callback URL PKPay ko batayein (ya yeh khud create-payment mein bhej diya jata hai):
                    <div class="mt-1 flex items-center gap-2">
                        <code class="block flex-1 truncate rounded bg-slate-100 px-2 py-1 text-xs">{{ $callbackUrl }}</code>
                        <button type="button" class="a-btn-light !px-2 !py-1 text-xs" onclick="navigator.clipboard.writeText('{{ $callbackUrl }}')">Copy</button>
                    </div>
                </li>
                <li>Upar "Save" karne ke baad neeche <strong>Test connection</strong> se check karein.</li>
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
                <li>Secret server par rehti hai, browser ko kabhi nahi bheji jati.</li>
                <li>Har callback HMAC signature se verify hota hai; sandbox callbacks se plan active nahi hota.</li>
                <li>Amount match hone par hi plan activate hota hai.</li>
                <li>Callback miss ho jaye to Payments page par "Check status" se dobara verify ho jata hai.</li>
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
                <tr><td colspan="6" class="py-8 text-center text-slate-500">Abhi koi online payment nahi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
