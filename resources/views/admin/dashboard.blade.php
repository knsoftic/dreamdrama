@extends('layouts.admin')
@section('title', 'Dashboard')
@section('actions')
    <a href="{{ route('admin.videos.create') }}" class="a-btn-primary"><x-icon name="plus" class="size-4" stroke="2.5"/> <span class="hidden sm:inline">Video upload</span></a>
@endsection

@php
    $cards = [
        ['Users', number_format($stats['users']), '+'.$stats['new_users'].' aaj', 'users', 'bg-violet-100 text-violet-700'],
        ['Active aaj', number_format($stats['active_today']), $stats['flagged'].' flagged', 'bolt', 'bg-sky-100 text-sky-700'],
        ['Video views aaj', number_format($stats['views_today']), $stats['videos'].' videos', 'film', 'bg-fuchsia-100 text-fuchsia-700'],
        ['Ads dekhe aaj', number_format($stats['ads_today']), $stats['cycles_today'].' cycles complete', 'megaphone', 'bg-amber-100 text-amber-700'],
        ['Users ko diya aaj', money($stats['paid_out_today']), 'cycle earnings', 'coin', 'bg-emerald-100 text-emerald-700'],
        ['Wallets mein total', money($stats['wallet_liability']), 'users ka balance', 'wallet', 'bg-slate-200 text-slate-700'],
        ['Pending withdrawals', $stats['pending_withdrawals'], money($stats['pending_withdrawal_amount']), 'banknotes', 'bg-rose-100 text-rose-700'],
        ['Pending payments', $stats['pending_payments'], 'subscription requests', 'card', 'bg-indigo-100 text-indigo-700'],
    ];
    $maxBar = max(1, $days->max('revenue'), $days->max('payout'));
    $profit = $totals['revenue'] - $totals['payout'];
@endphp

@section('content')
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    @foreach ($cards as [$label, $value, $sub, $icon, $tone])
        <div class="a-card p-4">
            <div class="flex items-start justify-between gap-2">
                <div class="text-xs font-medium text-slate-500">{{ $label }}</div>
                <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $tone }}"><x-icon :name="$icon" class="size-4"/></span>
            </div>
            <div class="mt-2 text-xl font-bold text-slate-900 lg:text-2xl">{{ $value }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ $sub }}</div>
        </div>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="a-card p-5 xl:col-span-2">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-900">Ad revenue vs user payout (14 din)</h2>
                <p class="text-xs text-slate-500">Revenue aap <a href="{{ route('admin.revenue.index') }}" class="text-violet-600 underline">Ad Revenue</a> page par network report se daalte hain.</p>
            </div>
            <div class="text-right">
                <div class="text-xs text-slate-500">Profit</div>
                <div class="text-lg font-bold {{ $profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ money($profit) }}</div>
            </div>
        </div>
        <div class="mt-4 flex gap-4 text-xs">
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-violet-500"></span> Revenue {{ money($totals['revenue']) }}</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-amber-400"></span> Payout {{ money($totals['payout']) }}</span>
        </div>
        <div class="mt-4 flex h-48 items-end gap-1.5 overflow-x-auto sm:gap-2">
            @foreach ($days as $d)
                <div class="flex min-w-6 flex-1 flex-col items-center gap-1" title="{{ $d['date'] }} — Revenue {{ money($d['revenue']) }}, Payout {{ money($d['payout']) }}, {{ $d['ads'] }} ads">
                    <div class="flex h-40 w-full items-end justify-center gap-0.5">
                        <div class="w-1/2 max-w-3 rounded-t bg-violet-500" style="height: {{ max(2, round(160 * $d['revenue'] / $maxBar)) }}px"></div>
                        <div class="w-1/2 max-w-3 rounded-t bg-amber-400" style="height: {{ max(2, round(160 * $d['payout'] / $maxBar)) }}px"></div>
                    </div>
                    <div class="text-[10px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($d['date'])->format('d') }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="a-card">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="font-bold text-slate-900">Pending withdrawals</h2>
            <a href="{{ route('admin.withdrawals.index') }}" class="text-xs font-semibold text-violet-600">Sab dekhein</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($latestWithdrawals as $w)
                <div class="flex items-center justify-between px-5 py-3 text-sm">
                    <div><div class="font-semibold text-slate-900">{{ $w->user->name }}</div><div class="text-xs text-slate-500">{{ $w->method }} · {{ $w->created_at->diffForHumans() }}</div></div>
                    <div class="font-bold text-slate-900">{{ money($w->amount) }}</div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">Koi pending request nahi</div>
            @endforelse
        </div>
    </div>
</div>

<div class="mt-6 a-card">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <h2 class="font-bold text-slate-900">Naye users</h2>
        <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-violet-600">Sab dekhein</a>
    </div>
    <div class="overflow-x-auto">
        <table class="a-table">
            <thead><tr><th>Naam</th><th>Phone</th><th>Balance</th><th>IP</th><th>Join</th></tr></thead>
            <tbody>
            @forelse ($latestUsers as $u)
                <tr>
                    <td><a href="{{ route('admin.users.show', $u) }}" class="font-semibold text-slate-900 hover:text-violet-600">{{ $u->name }}</a>
                        @if ($u->is_flagged)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">FLAG</span>@endif</td>
                    <td>{{ $u->phone }}</td>
                    <td>{{ money($u->balance) }}</td>
                    <td class="font-mono text-xs">{{ $u->register_ip }}</td>
                    <td>{{ $u->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-slate-500">Abhi koi user nahi</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
