@extends('layouts.admin')
@section('title', $plan->exists ? 'Plan edit: '.$plan->name : 'Naya plan')

@php
    $globalVideos = (int) setting('videos_per_cycle', 10);
    $globalAds = (int) setting('ads_per_cycle', 10);
@endphp

@section('content')
<form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}"
      x-data="{
          cycles: {{ (int) old('daily_cycle_limit', $plan->daily_cycle_limit) }},
          vpc: '{{ old('videos_per_cycle', $plan->videos_per_cycle) }}',
          apc: '{{ old('ads_per_cycle', $plan->ads_per_cycle) }}',
          withdraw: {{ old('withdrawals_enabled', $plan->withdrawals_enabled) ? 'true' : 'false' }},
          get videos() { return (parseInt(this.vpc) || {{ $globalVideos }}) * (parseInt(this.cycles) || 0) },
          get ads() { return (this.apc === '' ? {{ $globalAds }} : parseInt(this.apc) || 0) * (parseInt(this.cycles) || 0) },
      }"
      class="max-w-4xl space-y-5">
    @csrf
    @if ($plan->exists) @method('PUT') @endif

    {{-- Basic --}}
    <div class="a-card space-y-4 p-5">
        <h2 class="font-bold text-slate-900">1. Plan, price aur muddat</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="a-label">Plan naam</label><input name="name" value="{{ old('name', $plan->name) }}" class="a-input" required></div>
            <div><label class="a-label">Color</label><input name="color" type="color" value="{{ old('color', $plan->color) }}" class="a-input h-10 !p-1"></div>
            <div><label class="a-label">Price ({{ setting('currency_symbol') }}) — 0 = free</label><input name="price" type="number" step="0.01" min="0" value="{{ old('price', $plan->price ?? 0) }}" class="a-input" required></div>
            <div><label class="a-label">Kitne din baad expire ho — 0 = kabhi nahi</label><input name="duration_days" type="number" min="0" value="{{ old('duration_days', $plan->duration_days) }}" class="a-input" required></div>
        </div>
        <div><label class="a-label">Description (users ko dikhegi)</label><textarea name="description" rows="2" class="a-input">{{ old('description', $plan->description) }}</textarea></div>
    </div>

    {{-- Videos & earning --}}
    <div class="a-card space-y-4 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-bold text-slate-900">2. Videos aur earning</h2>
            <span class="rounded-lg bg-violet-50 px-3 py-1 text-sm font-semibold text-violet-700">Roz: <span x-text="videos"></span> videos + <span x-text="ads"></span> ads</span>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="a-label">Roz kitni cycles</label><input name="daily_cycle_limit" type="number" min="1" x-model="cycles" class="a-input" required></div>
            <div>
                <label class="a-label">Ek cycle mein videos</label>
                <input name="videos_per_cycle" type="number" min="1" x-model="vpc" class="a-input" placeholder="{{ $globalVideos }} (global)">
            </div>
            <div>
                <label class="a-label">Ek cycle mein ads</label>
                <input name="ads_per_cycle" type="number" min="0" x-model="apc" class="a-input" placeholder="{{ $globalAds }} (global)">
            </div>
        </div>
        <p class="text-xs text-slate-500">Videos / ads khali chhorein to Settings wali global value lagegi. Bara plan = kam ads rakh sakte hain.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="a-label">Earning multiplier (1 = normal, 1.5 = 50% zyada)</label><input name="rate_multiplier" type="number" step="0.05" min="0.1" value="{{ old('rate_multiplier', $plan->rate_multiplier) }}" class="a-input" required></div>
            <div><label class="a-label">Roz zyada se zyada earning — 0 = koi limit nahi</label><input name="daily_earning_cap" type="number" step="0.01" min="0" value="{{ old('daily_earning_cap', $plan->daily_earning_cap) }}" class="a-input" required></div>
        </div>
    </div>

    {{-- Withdrawal rules --}}
    <div class="a-card space-y-4 p-5">
        <h2 class="font-bold text-slate-900">3. Withdrawal ki shartein</h2>
        <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
            <input type="hidden" name="withdrawals_enabled" value="0">
            <input type="checkbox" name="withdrawals_enabled" value="1" x-model="withdraw" class="size-5 accent-violet-600">
            <span>
                <span class="block text-sm font-semibold text-slate-900">Is plan mein withdrawal allowed hai</span>
                <span class="block text-xs text-slate-500">Off karein to user earn karega lekin withdraw ke liye upgrade karna hoga</span>
            </span>
        </label>
        <div class="grid gap-4 sm:grid-cols-2" :class="withdraw ? '' : 'opacity-50'">
            <div><label class="a-label">Minimum withdrawal</label><input name="min_withdrawal" type="number" step="0.01" min="0" value="{{ old('min_withdrawal', $plan->min_withdrawal) }}" class="a-input" required></div>
            <div><label class="a-label">Maximum withdrawal (ek dafa mein) — 0 = koi limit nahi</label><input name="max_withdrawal" type="number" step="0.01" min="0" value="{{ old('max_withdrawal', $plan->max_withdrawal ?? 0) }}" class="a-input" required></div>
            <div><label class="a-label">Do withdrawals ke beech kitne din — 0 = koi faasla nahi</label><input name="withdrawal_gap_days" type="number" min="0" value="{{ old('withdrawal_gap_days', $plan->withdrawal_gap_days ?? 0) }}" class="a-input" required></div>
            <div>
                <label class="a-label">Withdrawal se pehle kitne active invites zaroori — 0 = koi shart nahi</label>
                <input name="required_referrals" type="number" min="0" value="{{ old('required_referrals', $plan->required_referrals ?? 0) }}" class="a-input" required>
            </div>
        </div>
        <p class="text-xs text-slate-500">Invite "active" tab ginta hai jab naya user kam az kam {{ (int) setting('referral_min_cycles', 1) }} cycle poori kare (<a href="{{ route('admin.settings.edit') }}" class="text-violet-600 underline">Settings → Invites</a>).</p>
    </div>

    {{-- Visibility --}}
    <div class="a-card flex flex-wrap items-center gap-6 p-5">
        <div class="w-32"><label class="a-label">Sort order</label><input name="sort_order" type="number" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="a-input"></div>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="is_active" value="1" class="size-4 accent-violet-600" @checked(old('is_active', $plan->is_active))> Active (users ko dikhe)</label>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="is_default" value="1" class="size-4 accent-violet-600" @checked(old('is_default', $plan->is_default))> Default plan (naye users + expire hone par)</label>
    </div>

    <div class="flex gap-2">
        <button class="a-btn-primary">Save</button>
        <a href="{{ route('admin.plans.index') }}" class="a-btn-light">Cancel</a>
    </div>
</form>
@endsection
