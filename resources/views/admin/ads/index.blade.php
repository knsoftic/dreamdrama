@extends('layouts.admin')
@section('title', 'Ad Units')
@section('actions')
    <a href="{{ route('admin.ads.create') }}" class="a-btn-primary"><x-icon name="plus" class="size-4" stroke="2.5"/> Ad unit</a>
@endsection

@section('content')
<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    <strong>Which code goes where?</strong>
    <ul class="mt-2 list-disc space-y-1 pl-5">
        <li><strong>Ad Units (this page) = banner / display ads only.</strong> They are shown inside a box in the video ad-break. Use an Adsterra "Banner" / "Native Banner" or a Monetag <em>Banner</em> zone — code that actually draws an ad in a fixed box.</li>
        <li><strong>Monetag Multitag, OnClick (pop-under), Push, In-Page Push, Vignette and Adsterra Social Bar / Popunder do NOT render in a box.</strong> They run across the whole site and open on a click. Paste those in <a href="{{ route('admin.settings.edit') }}" class="font-semibold underline">Settings → Ad Network Scripts</a>, not here.</li>
        <li>Never reward a click on an ad — networks treat that as incentivised traffic and can ban the account. The user only has to <em>watch</em> the countdown.</li>
    </ul>
</div>

<div class="a-card overflow-x-auto">
    <table class="a-table">
        <thead><tr><th>Name</th><th>Network</th><th>Placement</th><th>Impressions</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($units as $unit)
            <tr>
                <td class="font-semibold text-slate-900">{{ $unit->name }}</td>
                <td>{{ \App\Models\AdUnit::NETWORKS[$unit->network] ?? $unit->network }}</td>
                <td>{{ $unit->placement === 'ad_break' ? 'Ad Break' : 'Page Banner' }}</td>
                <td>{{ number_format($unit->impressions) }}</td>
                <td>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $unit->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $unit->is_active ? 'Active' : 'Off' }}</span>
                </td>
                <td class="text-right whitespace-nowrap">
                    <a href="{{ route('admin.ads.edit', $unit) }}" class="a-btn-light !px-2.5 !py-1.5 text-xs">Edit</a>
                    <form method="POST" action="{{ route('admin.ads.destroy', $unit) }}" class="inline" onsubmit="return confirm('Delete this ad unit?')">@csrf @method('DELETE')
                        <button class="a-btn-light !px-2.5 !py-1.5 text-xs text-rose-600">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-10 text-center text-slate-500">No ad units yet. A placeholder is shown in the ad-break until you add one.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
