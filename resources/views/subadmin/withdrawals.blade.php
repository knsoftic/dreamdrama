@extends('layouts.panel')
@section('title', 'Withdrawals')

@php
    $canWithdraw = setting('withdrawals_enabled', '1') === '1' && ! $pending && $available >= $min;
    $statusTone = ['pending' => 'bg-amber-100 text-amber-700', 'paid' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-rose-100 text-rose-700'];
@endphp

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-1">
        <div class="a-card p-5">
            <div class="text-xs text-slate-500">Available to withdraw</div>
            <div class="mt-1 text-3xl font-extrabold text-slate-900">{{ money($available) }}</div>
            <div class="mt-3 flex justify-between text-sm">
                <div><div class="text-xs text-slate-500">Total earned</div><div class="font-semibold">{{ money($totalEarned) }}</div></div>
                <div><div class="text-xs text-slate-500">Withdrawn</div><div class="font-semibold">{{ money($totalWithdrawn) }}</div></div>
            </div>
        </div>

        <form method="POST" action="{{ route('team.withdrawals.store') }}" class="a-card space-y-3 p-5">
            @csrf
            <h3 class="font-bold text-slate-900">Request withdrawal</h3>
            @if (! $canWithdraw)
                <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                    @if (setting('withdrawals_enabled', '1') !== '1') Withdrawals are closed right now.
                    @elseif ($pending) You already have a pending request.
                    @else You need at least {{ money($min) }} available. @endif
                </p>
            @else
                <div><label class="a-label">Amount ({{ money($min, 0) }} – {{ money(floor($available), 0) }})</label>
                    <input name="amount" type="number" step="1" min="{{ $min }}" max="{{ floor($available) }}" value="{{ old('amount', floor($available)) }}" class="a-input" required>
                    @error('amount')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label class="a-label">Method</label><select name="method" class="a-input">@foreach ($methods as $m)<option @selected(old('method') === $m)>{{ $m }}</option>@endforeach</select></div>
                <div><label class="a-label">Account title</label><input name="account_title" value="{{ old('account_title') }}" class="a-input" required></div>
                <div><label class="a-label">Account / mobile number</label><input name="account_number" value="{{ old('account_number') }}" class="a-input" required>
                    @error('account_number')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <button class="a-btn-primary w-full">Send request</button>
            @endif
        </form>
    </div>

    <div class="a-card overflow-x-auto lg:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Withdrawal history</div>
        <table class="a-table">
            <thead><tr><th>Amount</th><th>Method</th><th>Account</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            @forelse ($withdrawals as $w)
                <tr>
                    <td class="font-semibold">{{ money($w->amount) }}</td>
                    <td>{{ $w->method }}</td>
                    <td class="text-xs">{{ $w->account_title }}<div class="font-mono text-slate-400">{{ $w->account_number }}</div></td>
                    <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusTone[$w->status] ?? '' }}">{{ ucfirst($w->status) }}</span>
                        @if ($w->admin_note)<div class="mt-0.5 text-[10px] text-slate-400">{{ $w->admin_note }}</div>@endif</td>
                    <td class="whitespace-nowrap text-xs">{{ $w->created_at->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-slate-500">No withdrawals yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $withdrawals->links() }}</div>
    </div>
</div>
@endsection
