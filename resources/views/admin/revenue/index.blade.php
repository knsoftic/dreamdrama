@extends('layouts.admin')
@section('title', 'Ad Revenue')

@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    <form method="POST" action="{{ route('admin.revenue.store') }}" class="a-card space-y-3 self-start p-5">
        @csrf
        <h3 class="font-bold text-slate-900">Revenue entry add karein</h3>
        <p class="text-xs text-slate-500">Adsterra / Monetag dashboard se roz ki earning yahan daalein ({{ setting('currency_symbol') }} mein convert kar ke). Dashboard par profit isi se nikalta hai.</p>
        <div><label class="a-label">Date</label><input name="date" type="date" value="{{ today()->toDateString() }}" class="a-input" required></div>
        <div><label class="a-label">Network</label>
            <select name="network" class="a-input">@foreach (\App\Models\AdUnit::NETWORKS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div><label class="a-label">Amount ({{ setting('currency_symbol') }})</label><input name="amount" type="number" step="0.01" min="0" class="a-input" required></div>
        <div><label class="a-label">Impressions (optional)</label><input name="impressions" type="number" min="0" class="a-input"></div>
        <div><label class="a-label">Note</label><input name="note" class="a-input" placeholder="e.g. $3.20 @ 280"></div>
        <button class="a-btn-primary w-full">Add</button>
    </form>

    <div class="a-card overflow-x-auto xl:col-span-2">
        <table class="a-table">
            <thead><tr><th>Date</th><th>Network</th><th>Amount</th><th>Impressions</th><th>Note</th><th></th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr>
                    <td>{{ $e->date->format('d M Y') }}</td>
                    <td>{{ \App\Models\AdUnit::NETWORKS[$e->network] ?? $e->network }}</td>
                    <td class="font-semibold">{{ money($e->amount) }}</td>
                    <td>{{ number_format($e->impressions) }}</td>
                    <td class="text-xs">{{ $e->note }}</td>
                    <td class="text-right">
                        <form method="POST" action="{{ route('admin.revenue.destroy', $e) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')
                            <button class="text-xs font-semibold text-rose-600">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-slate-500">Abhi koi entry nahi</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
