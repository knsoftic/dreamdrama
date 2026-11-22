@extends('layouts.panel')
@section('title', 'Dashboard')

@php
    $cards = [
        ['My users', number_format($total), 'in your network', 'users', 'bg-violet-100 text-violet-700'],
        ['Active today', number_format($activeToday), 'opened the app', 'bolt', 'bg-sky-100 text-sky-700'],
        ['Paid users', number_format($paid), 'on a paid plan', 'crown', 'bg-emerald-100 text-emerald-700'],
        ['Free users', number_format($free), 'on the free plan', 'user', 'bg-slate-200 text-slate-700'],
    ];
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

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Downline levels (3-level MLM) --}}
    <div class="a-card p-5">
        <h2 class="font-bold text-slate-900">Downline (3 levels)</h2>
        <p class="text-xs text-slate-500">People invited through your code, by depth.</p>
        <div class="mt-4 space-y-3">
            @foreach ([1 => 'Level 1 — you invited', 2 => 'Level 2 — invited by level 1', 3 => 'Level 3 — invited by level 2'] as $lvl => $label)
                <div class="flex items-center gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-violet-100 text-sm font-bold text-violet-700">{{ $lvl }}</span>
                    <span class="flex-1 text-sm text-slate-600">{{ $label }}</span>
                    <span class="text-lg font-bold text-slate-900">{{ number_format($levels[$lvl]) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Plan breakdown --}}
    <div class="a-card p-5">
        <h2 class="font-bold text-slate-900">By plan</h2>
        <div class="mt-4 space-y-2 text-sm">
            <div class="flex items-center justify-between">
                <span class="inline-flex items-center gap-2"><span class="size-3 rounded-full bg-slate-400"></span> Free</span>
                <span class="font-semibold">{{ number_format($free) }}</span>
            </div>
            @foreach ($planRows as $row)
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-2"><span class="size-3 rounded-full" style="background: {{ $row['color'] }}"></span> {{ $row['name'] }}</span>
                    <span class="font-semibold">{{ number_format($row['count']) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Network earnings --}}
    <div class="a-card p-5">
        <h2 class="font-bold text-slate-900">Network earnings</h2>
        <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Total earned</span><span class="font-bold text-slate-900">{{ money($networkEarned) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">In wallets now</span><span class="font-semibold">{{ money($networkBalance) }}</span></div>
        </div>
        <div class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
            Share your invite code <strong class="font-mono text-slate-700">{{ $me->referral_code }}</strong> — everyone who joins with it (and the people they invite) appears in your network.
        </div>
    </div>
</div>

{{-- Recent joins --}}
<div class="mt-6 a-card">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <h2 class="font-bold text-slate-900">Recent joins</h2>
        <a href="{{ route('team.users.index') }}" class="text-xs font-semibold text-violet-600">View all</a>
    </div>
    <div class="overflow-x-auto">
        <table class="a-table">
            <thead><tr><th>Name</th><th>Phone</th><th>Invited by</th><th>Joined</th></tr></thead>
            <tbody>
            @forelse ($recent as $u)
                <tr>
                    <td class="font-semibold text-slate-900">{{ $u->name }}</td>
                    <td>{{ $u->phone }}</td>
                    <td>{{ $u->referrer?->name ?? '—' }}</td>
                    <td>{{ $u->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-slate-500">No users yet. Share your invite code to grow your network.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
