@extends('layouts.app')
@section('title', 'Plans')
@section('subtitle', 'Zyada cycles, zyada rate — apna plan chunein')

@php
    $statusColors = ['pending' => 'bg-amber-500/15 text-amber-300', 'approved' => 'bg-emerald-500/15 text-emerald-300', 'rejected' => 'bg-rose-500/15 text-rose-300'];
@endphp

@section('content')
<div class="space-y-5">
    <div class="card flex items-center gap-4 p-4">
        <div class="grid size-12 shrink-0 place-items-center rounded-2xl" style="background: {{ $current->color }}22; color: {{ $current->color }}">
            <x-icon name="crown" class="size-6"/>
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-xs text-app-muted">Aap ka current plan</div>
            <div class="font-bold">{{ $current->name }}</div>
        </div>
        <div class="text-right text-xs text-app-muted">
            @if ($subscription?->ends_at)
                Expire: <span class="font-semibold text-white">{{ $subscription->ends_at->format('d M Y') }}</span>
            @else
                Hamesha ke liye
            @endif
        </div>
    </div>

    @if ($pending)
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
            <strong>{{ $pending->plan->name }}</strong> ki payment ({{ money($pending->amount) }}) review mein hai. Admin verify kar ke plan active kar dega.
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($plans as $plan)
            @php $isCurrent = $plan->id === $current->id; @endphp
            <div class="relative flex flex-col overflow-hidden rounded-3xl border bg-app-card p-5 {{ $isCurrent ? 'border-brand ring-2 ring-brand/30' : 'border-app-line' }}">
                <div class="absolute inset-x-0 top-0 h-1" style="background: {{ $plan->color }}"></div>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-extrabold" style="color: {{ $plan->color }}">{{ $plan->name }}</h3>
                    @if ($isCurrent)<span class="chip bg-brand/20 text-brand">Current</span>@endif
                </div>
                <div class="mt-3">
                    @if ($plan->isFree())
                        <span class="text-3xl font-extrabold">Free</span>
                    @else
                        <span class="text-3xl font-extrabold">{{ money($plan->price, 0) }}</span>
                        <span class="text-sm text-app-muted">/ {{ $plan->duration_days > 0 ? $plan->duration_days.' din' : 'lifetime' }}</span>
                    @endif
                </div>
                @if ($plan->description)<p class="mt-2 text-sm text-app-muted">{{ $plan->description }}</p>@endif

                <ul class="mt-4 flex-1 space-y-2.5 text-sm">
                    <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-emerald-400" stroke="3"/> Roz <strong>{{ $plan->daily_cycle_limit }}</strong> cycles</li>
                    <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-emerald-400" stroke="3"/> <strong>{{ rtrim(rtrim(number_format($plan->rate_multiplier, 2), '0'), '.') }}x</strong> earning rate</li>
                    <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-emerald-400" stroke="3"/> Min withdrawal {{ money($plan->min_withdrawal, 0) }}</li>
                    <li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-emerald-400" stroke="3"/>
                        {{ (float) $plan->daily_earning_cap > 0 ? 'Daily cap '.money($plan->daily_earning_cap, 0) : 'Koi daily cap nahi' }}</li>
                </ul>

                <div class="mt-5">
                    @if ($isCurrent)
                        <button disabled class="btn-ghost w-full">Active plan</button>
                    @elseif ($plan->isFree())
                        <button disabled class="btn-ghost w-full">Free plan</button>
                    @elseif ($pending)
                        <button disabled class="btn-ghost w-full">Payment review mein</button>
                    @else
                        <a href="{{ route('plans.checkout', $plan) }}" class="btn-primary w-full">Plan khareedein</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($payments->isNotEmpty())
        <div class="card overflow-hidden">
            <div class="border-b border-app-line px-5 py-4 font-bold">Meri payments</div>
            <div class="divide-y divide-app-line">
                @foreach ($payments as $p)
                    <div class="flex items-center justify-between gap-3 px-5 py-3.5 text-sm">
                        <div>
                            <div class="font-semibold">{{ $p->plan->name }} · {{ money($p->amount) }}</div>
                            <div class="text-xs text-app-muted">{{ $p->method }} · TID {{ $p->transaction_id }} · {{ $p->created_at->format('d M, h:i A') }}</div>
                            @if ($p->admin_note)<div class="mt-0.5 text-xs text-app-muted">Note: {{ $p->admin_note }}</div>@endif
                        </div>
                        <span class="chip {{ $statusColors[$p->status] ?? '' }}">{{ ucfirst($p->status) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
