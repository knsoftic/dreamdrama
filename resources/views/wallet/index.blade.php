@extends('layouts.app')
@section('title', 'Wallet')
@section('subtitle', 'Balance, withdrawals aur transactions')

@php
    $statusColors = ['pending' => 'bg-amber-500/15 text-amber-300', 'paid' => 'bg-emerald-500/15 text-emerald-300', 'rejected' => 'bg-rose-500/15 text-rose-300'];
    $canWithdraw = setting('withdrawals_enabled', '1') === '1' && ! $pendingWithdrawal && (float) $user->balance >= (float) $plan->min_withdrawal;
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
        <p class="relative mt-2 text-xs text-white/75">Kam az kam {{ money($plan->min_withdrawal, 0) }} ({{ $plan->name }} plan)</p>
    </div>

    <x-ad-banner :unit="$banner" class="lg:self-start"/>

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
             class="relative w-full max-w-md rounded-t-3xl border border-app-line bg-app-card p-5 safe-bottom lg:rounded-3xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-white/20 lg:hidden"></div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold">Withdraw</h3>
                <button @click="sheet = false" class="grid size-9 place-items-center rounded-full bg-app-card2"><x-icon name="x" class="size-4"/></button>
            </div>

            @if (! $canWithdraw)
                <div class="mt-4 rounded-xl bg-amber-500/10 p-3 text-sm text-amber-200">
                    @if (setting('withdrawals_enabled', '1') !== '1')
                        Withdrawals abhi band hain.
                    @elseif ($pendingWithdrawal)
                        Aap ki ek request pehle se pending hai. Us ke process hone ka intezar karein.
                    @else
                        Withdraw ke liye kam az kam {{ money($plan->min_withdrawal) }} balance chahiye. Abhi {{ money($user->balance) }} hai.
                    @endif
                </div>
            @else
                <form method="POST" action="{{ route('wallet.withdraw') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="app-label">Amount</label>
                        <input name="amount" type="number" step="1" min="{{ (float) $plan->min_withdrawal }}" max="{{ floor((float) $user->balance) }}"
                               value="{{ old('amount', floor((float) $user->balance)) }}" class="app-input" required inputmode="numeric">
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
