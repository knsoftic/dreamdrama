@extends('layouts.panel')
@section('title', 'Deposits')

@section('content')
<form class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="User name or phone" class="a-input max-w-xs">
    <select name="status" class="a-input !w-auto">
        <option value="">All</option>
        <option value="approved" @selected($status === 'approved')>Received (approved)</option>
        <option value="pending" @selected($status === 'pending')>Pending</option>
        <option value="rejected" @selected($status === 'rejected')>Failed / rejected</option>
    </select>
    <button class="a-btn-light">Filter</button>
</form>

<div class="a-card overflow-x-auto">
    <table class="a-table">
        <thead><tr>
            <th>User</th><th>Plan</th><th>Amount</th><th>Tax</th><th>Your net</th>
            <th>Method</th><th>Status</th><th>Callback</th><th>Date</th>
        </tr></thead>
        <tbody>
        @forelse ($deposits as $d)
            @php
                $statusTone = ['approved' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'rejected' => 'bg-rose-100 text-rose-700'][$d->status] ?? 'bg-slate-100 text-slate-600';
            @endphp
            <tr>
                <td class="font-semibold text-slate-900">{{ $d->user->name }}<div class="text-xs font-normal text-slate-500">{{ $d->user->phone }}</div></td>
                <td><span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full" style="background: {{ $d->plan->color }}"></span>{{ $d->plan->name }}</span></td>
                <td class="font-semibold">{{ money($d->amount) }}</td>
                <td class="text-xs text-slate-500">{{ rtrim(rtrim(number_format($d->tax_percent, 2), '0'), '.') }}%<div>-{{ money($d->taxAmount()) }}</div></td>
                <td class="font-semibold text-emerald-700">{{ $d->status === 'approved' ? money($d->netAmount()) : '—' }}</td>
                <td class="text-xs">{{ $d->method }}@if ($d->transaction_id)<div class="font-mono text-slate-400">{{ \Illuminate\Support\Str::limit($d->transaction_id, 16) }}</div>@endif</td>
                <td>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusTone }}">{{ $d->status === 'approved' ? 'Received' : ucfirst($d->status) }}</span>
                    @if ($d->isGateway() && $d->gateway_status)<div class="mt-0.5 text-[10px] text-slate-400">gateway: {{ $d->gateway_status }}</div>@endif
                </td>
                <td class="text-xs">
                    @if (! $d->isGateway())
                        <span class="text-slate-400">manual</span>
                    @elseif ($d->callback_at)
                        <span class="text-emerald-600">✓ {{ $d->callback_at->format('d M, h:i A') }}</span>
                    @else
                        <span class="text-amber-600">not received</span>
                    @endif
                </td>
                <td class="whitespace-nowrap text-xs">{{ ($d->reviewed_at ?? $d->created_at)->format('d M Y') }}<div class="text-slate-400">{{ ($d->reviewed_at ?? $d->created_at)->format('h:i A') }}</div></td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-10 text-center text-slate-500">No deposits yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $deposits->links() }}</div>
<p class="mt-3 text-xs text-slate-500">"Your net" is the amount after the platform tax, and is added to your balance only when a deposit is received (approved).</p>
@endsection
