@extends('layouts.app')
@section('title', 'Payment')
@section('back', route('plans'))

@php
    $status = $payment->status === 'approved' ? 'paid' : ($payment->gateway_status ?? 'pending');
    $isPaid = $payment->status === 'approved';
    $isPending = ! $isPaid && in_array($status, ['pending', 'creating'], true);
@endphp

@section('content')
<div class="mx-auto max-w-md pt-6">
    @if ($isPaid)
        <div class="card p-6 text-center">
            <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-emerald-500/15 text-emerald-400"><x-icon name="check" class="size-8" stroke="2.5"/></div>
            <h2 class="mt-4 text-xl font-extrabold">Payment complete!</h2>
            <p class="mt-1 text-sm text-app-muted">{{ $payment->plan->name }} plan is now active.</p>
            <div class="mt-4 rounded-xl bg-app-card2 p-3 text-sm">
                <div class="flex justify-between"><span class="text-app-muted">Amount</span><span class="font-semibold">{{ money($payment->amount) }}</span></div>
                <div class="mt-1 flex justify-between"><span class="text-app-muted">Transaction</span><span class="font-mono text-xs">{{ $payment->transaction_id }}</span></div>
            </div>
            <a href="{{ route('feed') }}" class="btn-primary mt-6 w-full">Start watching videos</a>
        </div>
    @elseif ($isPending)
        <div x-data="{ left: 20 }" x-init="let t = setInterval(() => { if (--left <= 0) { clearInterval(t); location.reload(); } }, 1000)"
             class="card p-6 text-center">
            <div class="mx-auto size-14 animate-spin rounded-full border-4 border-white/15 border-t-brand"></div>
            <h2 class="mt-4 text-xl font-extrabold">Checking your payment…</h2>
            <p class="mt-1 text-sm text-app-muted">If you've paid, it will confirm shortly.</p>
            <p class="mt-3 text-xs text-app-muted"><span x-text="left"></span> seconds until the next check…</p>
            <div class="mt-5 flex gap-2">
                <button onclick="location.reload()" class="btn-ghost flex-1">Refresh now</button>
                <a href="{{ route('plans') }}" class="btn-ghost flex-1">Plans</a>
            </div>
        </div>
    @else
        <div class="card p-6 text-center">
            <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-rose-500/15 text-rose-400"><x-icon name="x" class="size-8" stroke="2.5"/></div>
            <h2 class="mt-4 text-xl font-extrabold">Payment not completed</h2>
            <p class="mt-1 text-sm text-app-muted">
                {{ $status === 'expired' ? 'The QR expired.' : 'The payment was cancelled or failed.' }}
                Try again.
            </p>
            <a href="{{ route('plans.checkout', $payment->plan) }}" class="btn-primary mt-6 w-full">Try again</a>
            <a href="{{ route('plans') }}" class="mt-2 block text-sm text-app-muted">Back to plans</a>
        </div>
    @endif
</div>
@endsection
