@extends('layouts.admin')
@section('title', $unit->exists ? 'Edit ad unit' : 'New ad unit')

@section('content')
<form method="POST" action="{{ $unit->exists ? route('admin.ads.update', $unit) : route('admin.ads.store') }}" class="a-card max-w-3xl space-y-4 p-5">
    @csrf
    @if ($unit->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="a-label">Name</label>
            <input name="name" value="{{ old('name', $unit->name) }}" class="a-input" required placeholder="Adsterra 300x250 #1">
        </div>
        <div>
            <label class="a-label">Network</label>
            <select name="network" class="a-input">
                @foreach (\App\Models\AdUnit::NETWORKS as $k => $v)<option value="{{ $k }}" @selected(old('network', $unit->network) === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="a-label">Placement</label>
            <select name="placement" class="a-input">
                @foreach (\App\Models\AdUnit::PLACEMENTS as $k => $v)<option value="{{ $k }}" @selected(old('placement', $unit->placement) === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="a-label">Priority</label>
            <input name="sort_order" type="number" value="{{ old('sort_order', $unit->sort_order ?? 0) }}" class="a-input">
        </div>
    </div>
    <div>
        <label class="a-label">Ad code (HTML / JavaScript)</label>
        <textarea name="code" rows="10" class="a-input font-mono text-xs" required placeholder="<script type=&quot;text/javascript&quot;>atOptions = {...}</script>">{{ old('code', $unit->code) }}</textarea>
        <p class="mt-1 text-xs text-slate-500">Paste a <strong>banner / display</strong> ad code (it renders in a fixed box). The code runs in an isolated frame. Pop-under / push / Multitag codes will not show here — put those in Settings → Ad Network Scripts.</p>
    </div>
    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
        <input type="checkbox" name="is_active" value="1" class="size-4 accent-violet-600" @checked(old('is_active', $unit->is_active))> Active
    </label>
    <div class="flex gap-2">
        <button class="a-btn-primary">Save</button>
        <a href="{{ route('admin.ads.index') }}" class="a-btn-light">Cancel</a>
    </div>
</form>
@endsection
