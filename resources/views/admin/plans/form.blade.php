@extends('layouts.admin')
@section('title', $plan->exists ? 'Plan edit' : 'Naya plan')

@section('content')
<form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="a-card max-w-3xl space-y-4 p-5">
    @csrf
    @if ($plan->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="a-label">Plan naam</label><input name="name" value="{{ old('name', $plan->name) }}" class="a-input" required></div>
        <div><label class="a-label">Color</label><input name="color" type="color" value="{{ old('color', $plan->color) }}" class="a-input h-10 !p-1"></div>
        <div><label class="a-label">Price ({{ setting('currency_symbol') }}) — 0 = free</label><input name="price" type="number" step="0.01" min="0" value="{{ old('price', $plan->price ?? 0) }}" class="a-input" required></div>
        <div><label class="a-label">Muddat (din) — 0 = lifetime</label><input name="duration_days" type="number" min="0" value="{{ old('duration_days', $plan->duration_days) }}" class="a-input" required></div>
        <div><label class="a-label">Roz kitni cycles</label><input name="daily_cycle_limit" type="number" min="1" value="{{ old('daily_cycle_limit', $plan->daily_cycle_limit) }}" class="a-input" required></div>
        <div><label class="a-label">Earning multiplier (1 = normal, 1.5 = 50% zyada)</label><input name="rate_multiplier" type="number" step="0.05" min="0.1" value="{{ old('rate_multiplier', $plan->rate_multiplier) }}" class="a-input" required></div>
        <div><label class="a-label">Daily earning cap — 0 = no cap</label><input name="daily_earning_cap" type="number" step="0.01" min="0" value="{{ old('daily_earning_cap', $plan->daily_earning_cap) }}" class="a-input" required></div>
        <div><label class="a-label">Min withdrawal</label><input name="min_withdrawal" type="number" step="0.01" min="0" value="{{ old('min_withdrawal', $plan->min_withdrawal) }}" class="a-input" required></div>
        <div><label class="a-label">Sort order</label><input name="sort_order" type="number" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="a-input"></div>
    </div>
    <div><label class="a-label">Description</label><textarea name="description" rows="2" class="a-input">{{ old('description', $plan->description) }}</textarea></div>
    <div class="flex flex-wrap gap-6">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="is_active" value="1" class="size-4 accent-violet-600" @checked(old('is_active', $plan->is_active))> Active (users ko dikhe)</label>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="is_default" value="1" class="size-4 accent-violet-600" @checked(old('is_default', $plan->is_default))> Default plan (naye users)</label>
    </div>
    <div class="flex gap-2">
        <button class="a-btn-primary">Save</button>
        <a href="{{ route('admin.plans.index') }}" class="a-btn-light">Cancel</a>
    </div>
</form>
@endsection
