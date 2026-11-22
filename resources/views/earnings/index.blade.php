@extends('layouts.app')
@section('title', 'Earnings')
@section('subtitle', 'Your cycles and daily earnings')

@php
    $c = $state['cycle'];
    $max = max(0.0001, $days->max('total'));
@endphp

@section('content')
<div class="space-y-4 lg:grid lg:grid-cols-3 lg:gap-6 lg:space-y-0">
    {{-- Summary --}}
    <div class="grad relative overflow-hidden rounded-3xl p-5 shadow-lg shadow-brand/20 lg:col-span-2">
        <div class="absolute -right-10 -top-10 size-40 rounded-full bg-white/10"></div>
        <div class="text-sm text-white/80">Today's earning</div>
        <div class="mt-1 text-4xl font-extrabold">{{ money($state['today_earning'], 3) }}</div>
        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-2xl bg-black/20 p-3">
                <div class="text-lg font-bold">{{ $state['cycles_today'] }}/{{ $state['cycle_limit'] }}</div>
                <div class="text-[11px] text-white/75">Cycles today</div>
            </div>
            <div class="rounded-2xl bg-black/20 p-3">
                <div class="text-lg font-bold">{{ money($state['balance']) }}</div>
                <div class="text-[11px] text-white/75">Wallet</div>
            </div>
            <div class="rounded-2xl bg-black/20 p-3">
                <div class="text-lg font-bold">{{ rtrim(rtrim(number_format($state['plan']['multiplier'], 2), '0'), '.') }}x</div>
                <div class="text-[11px] text-white/75">{{ $state['plan']['name'] }}</div>
            </div>
        </div>
    </div>

    {{-- Current cycle --}}
    <div class="card p-5">
        <div class="flex items-center justify-between">
            <h3 class="font-bold">Current cycle</h3>
            <span class="chip bg-coin/15 text-coin">+{{ money($c['total'], 3) }}</span>
        </div>
        <div class="mt-4 space-y-3 text-sm">
            <div>
                <div class="mb-1 flex justify-between"><span class="text-app-muted">Videos</span><span class="font-semibold">{{ $c['videos_watched'] }}/{{ $c['videos_required'] }}</span></div>
                <div class="h-2 rounded-full bg-white/10"><div class="h-full rounded-full bg-brand" style="width: {{ min(100, 100 * $c['videos_watched'] / max(1, $c['videos_required'])) }}%"></div></div>
            </div>
            <div>
                <div class="mb-1 flex justify-between"><span class="text-app-muted">Ads</span><span class="font-semibold">{{ $c['ads_watched'] }}/{{ $c['ads_required'] }}</span></div>
                <div class="h-2 rounded-full bg-white/10"><div class="h-full rounded-full bg-brand-2" style="width: {{ $c['ads_required'] ? min(100, 100 * $c['ads_watched'] / $c['ads_required']) : 100 }}%"></div></div>
            </div>
            <div class="flex justify-between border-t border-app-line pt-3 text-xs text-app-muted">
                <span>Video: {{ money($c['video_earning'], 3) }}</span><span>Ads: {{ money($c['ad_earning'], 3) }}</span>
            </div>
        </div>
        <a href="{{ route('feed') }}" class="btn-primary mt-4 w-full"><x-icon name="play" class="size-4" solid/> Continue watching</a>
    </div>

    {{-- Last 7 days --}}
    <div class="card p-5 lg:col-span-2">
        <h3 class="font-bold">Last 7 days</h3>
        <div class="mt-5 flex h-40 items-end gap-2 sm:gap-4">
            @foreach ($days as $d)
                <div class="flex flex-1 flex-col items-center gap-2">
                    <div class="text-[10px] font-semibold text-app-muted">{{ $d['total'] > 0 ? number_format($d['total'], 2) : '' }}</div>
                    <div class="w-full max-w-10 rounded-t-lg {{ $d['date'] === today()->toDateString() ? 'grad' : 'bg-brand/40' }}"
                         style="height: {{ max(4, round(100 * $d['total'] / $max)) }}px"></div>
                    <div class="text-[11px] font-medium text-app-muted">{{ $d['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <x-ad-banner :unit="$banner" class="lg:self-start"/>

    {{-- History --}}
    <div class="card overflow-hidden lg:col-span-3">
        <div class="border-b border-app-line px-5 py-4 font-bold">Cycle history</div>
        <div class="divide-y divide-app-line">
            @forelse ($cycles as $cycle)
                <div class="flex items-center gap-3 px-5 py-3.5">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl {{ $cycle->status === 'completed' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-brand/15 text-brand' }}">
                        <x-icon :name="$cycle->status === 'completed' ? 'check' : 'clock'" class="size-5" stroke="2.2"/>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold">Cycle #{{ $cycle->id }}</div>
                        <div class="text-xs text-app-muted">
                            {{ $cycle->videos_watched }}/{{ $cycle->videos_required }} videos · {{ $cycle->ads_watched }}/{{ $cycle->ads_required }} ads ·
                            {{ ($cycle->completed_at ?? $cycle->created_at)->diffForHumans() }}
                        </div>
                    </div>
                    <div class="text-right">
                        @if ($cycle->status === 'completed')
                            <div class="text-sm font-bold text-emerald-400">+{{ money($cycle->credited_amount, 3) }}</div>
                            @if ((float) $cycle->credited_amount < (float) $cycle->total_earning)
                                <div class="text-[10px] text-amber-400">Daily cap</div>
                            @endif
                        @else
                            <div class="text-sm font-bold text-app-muted">{{ money($cycle->total_earning, 3) }}</div>
                            <div class="text-[10px] text-app-muted">Pending</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-app-muted">No cycles yet. Start watching videos!</div>
            @endforelse
        </div>
        @if ($cycles->hasPages())
            <div class="border-t border-app-line px-5 py-3">{{ $cycles->links('partials.pager') }}</div>
        @endif
    </div>
</div>
@endsection
