@extends('layouts.app')
@section('title', 'Checkout')
@section('back', route('plans'))
@section('subtitle', $plan->name.' plan khareedein')

@section('content')
<div class="mx-auto max-w-xl space-y-4">
    <div class="rounded-3xl p-5" style="background: linear-gradient(135deg, {{ $plan->color }}, {{ $plan->color }}99)">
        <div class="text-sm text-white/80">{{ $plan->name }} plan</div>
        <div class="mt-1 text-3xl font-extrabold">{{ money($plan->price, 0) }}</div>
        <div class="mt-1 text-sm text-white/85">{{ $plan->duration_days > 0 ? $plan->duration_days.' din' : 'Lifetime' }} · Roz {{ $plan->daily_cycle_limit }} cycles · {{ $plan->multiplierLabel() }} rate</div>
    </div>

    @error('payment')
        <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">{{ $message }}</div>
    @enderror

    {{-- Instant online payment via the gateway --}}
    @if ($gatewayOn)
        <div class="card overflow-hidden">
            <div class="flex items-center gap-3 border-b border-app-line p-5">
                <span class="grad grid size-11 place-items-center rounded-xl"><x-icon name="bolt" class="size-5"/></span>
                <div>
                    <h3 class="font-bold">Online payment (Raast QR)</h3>
                    <p class="text-xs text-app-muted">JazzCash / Easypaisa / bank app se QR scan karein — foran confirm.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('plans.pay-gateway', $plan) }}" class="p-5">
                @csrf
                <button class="btn-primary w-full !py-4 text-base">
                    <x-icon name="bolt" class="size-5"/> {{ money($plan->price, 0) }} abhi pay karein
                </button>
                <p class="mt-2 text-center text-xs text-app-muted">Aap gateway ke secure page par jayenge. Payment ke baad plan foran active ho jata hai.</p>
            </form>
        </div>
    @endif

    @if ($gatewayOn && $manualOn)
        <div class="flex items-center gap-3 text-xs text-app-muted">
            <span class="h-px flex-1 bg-app-line"></span> YA manually <span class="h-px flex-1 bg-app-line"></span>
        </div>
    @endif

    {{-- Manual payment (admin verifies) --}}
    @if ($manualOn)
        <div class="card p-5">
            <h3 class="flex items-center gap-2 font-bold"><x-icon name="card" class="size-5 text-brand"/> Manual payment</h3>
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
            <button class="{{ $gatewayOn ? 'btn-ghost' : 'btn-primary' }} w-full !py-3.5">Payment submit karein</button>
            <p class="text-center text-xs text-app-muted">Admin payment verify kar ke aap ka plan active kar dega.</p>
        </form>
    @endif
</div>
@endsection
