@extends('layouts.app')
@section('title', 'Wallet')
@section('subtitle', 'Balance, withdrawals aur transactions')

@php
    $statusColors = ['pending' => 'bg-amber-500/15 text-amber-300', 'paid' => 'bg-emerald-500/15 text-emerald-300', 'rejected' => 'bg-rose-500/15 text-rose-300'];
    $canWithdraw = $rules['allowed'];
    $actions = ['plans' => [route('plans'), 'Plan upgrade karein'], 'invite' => [route('invite'), 'Dost invite karein']];
@endphp

@section('content')
<div x-data="{ sheet: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-4 lg:grid lg:grid-cols-3 lg:gap-6 lg:space-y-0">

    <div class="grad relative overflow-hidden rounded-3xl p-5 shadow-lg shadow-brand/20 lg:col-span-2">
        <div class="absolute -right-12 -bottom-16 size-48 rounded-full bg-white/10"></div>
        <div class="text-sm text-white/80">Available balance</div>
        <div class="mt-1 text-4xl font-extrabold">{{ money($user->balance) }}</div>
        <div class="mt-4 flex gap-6 text-sm">
            <div><div class="text-white/70">Total kamaya</div><div class="font-bold">{{ money($user->total_earned) }}</div></div>
            <div><div class="text-white/70">Withdraw kiya</div><div class="font-bold">{{ money($user->total_withdrawn) }}</div></div>
        </div>
        <button @click="sheet = true" class="relative mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-black active:scale-[.98]">
            <x-icon name="download" class="size-4" stroke="2.2"/> Withdraw
        </button>
    </div>

    {{-- Withdrawal requirements for the user's plan --}}
    <div class="card p-5 lg:row-span-2 lg:self-start">
        <div class="flex items-center justify-between gap-2">
            <h3 class="font-bold">Withdrawal ki shartein</h3>
            <span class="chip {{ $canWithdraw ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300' }}">{{ $canWithdraw ? 'Ready' : 'Abhi nahi' }}</span>
        </div>
        <p class="mt-1 text-xs text-app-muted">{{ $plan->name }} plan ke hisaab se</p>
        <ul class="mt-4 space-y-3">
            @foreach ($rules['checks'] as $check)
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full {{ $check['ok'] ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                        <x-icon :name="$check['ok'] ? 'check' : 'x'" class="size-3.5" stroke="3"/>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold">{{ $check['label'] }}</div>
                        @if ($check['detail'])<div class="text-xs text-app-muted">{{ $check['detail'] }}</div>@endif
                        @if (! $check['ok'] && $check['action'] && isset($actions[$check['action']]))
                            <a href="{{ $actions[$check['action']][0] }}" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-brand">
                                {{ $actions[$check['action']][1] }} <x-icon name="chevron-right" class="size-3" stroke="2.5"/>
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($rules['plan_max'] > 0)
            <p class="mt-4 border-t border-app-line pt-3 text-xs text-app-muted">Ek dafa mein zyada se zyada {{ money($rules['plan_max'], 0) }} withdraw ho sakte hain.</p>
        @endif
    </div>

    <x-ad-banner :unit="$banner" class="lg:col-span-2"/>

    {{-- Withdrawals --}}
    <div class="card overflow-hidden lg:col-span-3">
        <div class="border-b border-app-line px-5 py-4 font-bold">Withdrawal requests</div>
        <div class="divide-y divide-app-line">
            @forelse ($withdrawals as $w)
                <div class="flex items-center justify-between gap-3 px-5 py-3.5 text-sm">
                    <div>
                        <div class="font-semibold">{{ money($w->amount) }} · {{ $w->method }}</div>
                        <div class="text-xs text-app-muted">{{ $w->account_number }} · {{ $w->created_at->format('d M, h:i A') }}</div>
                        @if ($w->admin_note)<div class="mt-0.5 text-xs text-app-muted">Note: {{ $w->admin_note }}</div>@endif
                    </div>
                    <span class="chip {{ $statusColors[$w->status] ?? '' }}">{{ ucfirst($w->status) }}</span>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-app-muted">Abhi koi withdrawal nahi.</div>
            @endforelse
        </div>
    </div>

    {{-- Transactions --}}
    <div class="card overflow-hidden lg:col-span-3">
        <div class="border-b border-app-line px-5 py-4 font-bold">Transactions</div>
        <div class="divide-y divide-app-line">
            @forelse ($transactions as $t)
                <div class="flex items-center gap-3 px-5 py-3.5">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl {{ $t->type === 'credit' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">
                        <x-icon :name="$t->type === 'credit' ? 'coin' : 'download'" class="size-5"/>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ $t->description ?: $t->sourceLabel() }}</div>
                        <div class="text-xs text-app-muted">{{ $t->created_at->format('d M Y, h:i A') }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold {{ $t->type === 'credit' ? 'text-emerald-400' : 'text-rose-400' }}">{{ $t->type === 'credit' ? '+' : '-' }}{{ money($t->amount, 3) }}</div>
                        <div class="text-[10px] text-app-muted">Bal {{ money($t->balance_after) }}</div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-app-muted">Abhi koi transaction nahi.</div>
            @endforelse
        </div>
        @if ($transactions->hasPages())
            <div class="border-t border-app-line px-5 py-3">{{ $transactions->links('partials.pager') }}</div>
        @endif
    </div>

    {{-- Withdraw bottom sheet (mobile) / modal (desktop) --}}
    <div x-show="sheet" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center lg:items-center" @keydown.escape.window="sheet = false">
        <div x-show="sheet" x-transition.opacity class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="sheet = false"></div>
        <div x-show="sheet" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full lg:translate-y-4 lg:opacity-0" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="translate-y-full lg:opacity-0"
             class="relative max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-t-3xl border border-app-line bg-app-card p-5 safe-bottom lg:rounded-3xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-white/20 lg:hidden"></div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold">Withdraw</h3>
                <button @click="sheet = false" class="grid size-9 place-items-center rounded-full bg-app-card2"><x-icon name="x" class="size-4"/></button>
            </div>

            @if (! $canWithdraw)
                <p class="mt-3 text-sm text-app-muted">Withdrawal ke liye yeh shartein poori karein:</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($rules['checks'] as $check)
                        <li class="flex items-start gap-2 rounded-xl px-3 py-2 text-sm {{ $check['ok'] ? 'bg-emerald-500/10 text-emerald-200' : 'bg-rose-500/10 text-rose-200' }}">
                            <x-icon :name="$check['ok'] ? 'check' : 'x'" class="mt-0.5 size-4 shrink-0" stroke="3"/>
                            <span>{{ $check['label'] }}@if ($check['detail']) <span class="opacity-75">— {{ $check['detail'] }}</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
                @php $firstAction = collect($rules['checks'])->first(fn ($c) => ! $c['ok'] && $c['action']); @endphp
                @if ($firstAction)
                    <a href="{{ $actions[$firstAction['action']][0] }}" class="btn-primary mt-4 w-full">{{ $actions[$firstAction['action']][1] }}</a>
                @endif
            @else
                <form method="POST" action="{{ route('wallet.withdraw') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="app-label">Amount <span class="text-app-muted">({{ money($rules['min'], 0) }} – {{ money($rules['max'], 0) }})</span></label>
                        <input name="amount" type="number" step="1" min="{{ $rules['min'] }}" max="{{ $rules['max'] }}"
                               value="{{ old('amount', $rules['max']) }}" class="app-input" required inputmode="numeric">
                        @error('amount')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="app-label">Method</label>
                        <select name="method" class="app-input">
                            @foreach ($methods as $m)<option @selected(old('method') === $m)>{{ $m }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="app-label">Account title</label>
                        <input name="account_title" value="{{ old('account_title', $user->name) }}" class="app-input" required>
                    </div>
                    <div>
                        <label class="app-label">Account / mobile number</label>
                        <input name="account_number" value="{{ old('account_number', $user->phone) }}" class="app-input" required>
                        @error('account_number')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn-primary w-full !py-3.5">Request bhejein</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
