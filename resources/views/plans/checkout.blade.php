@extends('layouts.app')
@section('title', 'Checkout')
@section('back', route('plans'))
@section('subtitle', $plan->name.' plan khareedein')

@section('content')
<div class="mx-auto max-w-xl space-y-4">
    <div class="rounded-3xl p-5" style="background: linear-gradient(135deg, {{ $plan->color }}, {{ $plan->color }}99)">
        <div class="text-sm text-white/80">{{ $plan->name }} plan</div>
        <div class="mt-1 text-3xl font-extrabold">{{ money($plan->price, 0) }}</div>
        <div class="mt-1 text-sm text-white/85">{{ $plan->duration_days > 0 ? $plan->duration_days.' din' : 'Lifetime' }} · Roz {{ $plan->daily_cycle_limit }} cycles · {{ rtrim(rtrim(number_format($plan->rate_multiplier, 2), '0'), '.') }}x rate</div>
    </div>

    <div class="card p-5">
        <h3 class="flex items-center gap-2 font-bold"><x-icon name="card" class="size-5 text-brand"/> Payment kaise karein</h3>
        <div class="mt-3 whitespace-pre-line rounded-xl bg-app-card2 p-4 text-sm leading-relaxed text-white/85">{{ setting('payment_instructions') }}</div>
    </div>

    <form method="POST" action="{{ route('plans.pay', $plan) }}" enctype="multipart/form-data" class="card space-y-4 p-5">
        @csrf
        <h3 class="font-bold">Payment details</h3>
        <div>
            <label class="app-label">Payment method</label>
            <select name="method" class="app-input" required>
                @foreach ($methods as $m)
                    <option value="{{ $m }}" @selected(old('method') === $m)>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="app-label">Transaction ID (TID)</label>
            <input name="transaction_id" value="{{ old('transaction_id') }}" class="app-input" required placeholder="e.g. 0123456789">
            @error('transaction_id')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="app-label">Jis number se payment ki <span class="text-app-muted">(optional)</span></label>
            <input name="sender_number" value="{{ old('sender_number') }}" class="app-input" inputmode="tel" placeholder="03XX XXXXXXX">
        </div>
        <div>
            <label class="app-label">Screenshot <span class="text-app-muted">(optional)</span></label>
            <input type="file" name="screenshot" accept="image/*" class="block w-full text-sm text-app-muted file:mr-3 file:rounded-lg file:border-0 file:bg-app-card2 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
            @error('screenshot')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full !py-3.5">Payment submit karein</button>
        <p class="text-center text-xs text-app-muted">Admin payment verify kar ke aap ka plan active kar dega.</p>
    </form>
</div>
@endsection
