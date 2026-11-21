@extends('layouts.admin')
@section('title', 'Plans')
@section('actions')
    <a href="{{ route('admin.plans.create') }}" class="a-btn-primary"><x-icon name="plus" class="size-4" stroke="2.5"/> Naya plan</a>
@endsection

@section('content')
<div class="a-card overflow-x-auto">
    <table class="a-table">
        <thead>
        <tr>
            <th>Plan</th><th>Price</th><th>Muddat</th><th>Roz videos</th><th>Rate</th>
            <th>Withdrawal</th><th>Min – Max</th><th>Invites</th><th>Gap</th><th>Users</th><th></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($plans as $plan)
            <tr class="{{ $plan->is_active ? '' : 'opacity-50' }}">
                <td class="whitespace-nowrap">
                    <span class="inline-flex items-center gap-2 font-semibold text-slate-900"><span class="size-3 rounded-full" style="background: {{ $plan->color }}"></span>{{ $plan->name }}</span>
                    @if ($plan->is_default)<span class="ml-1 rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold text-violet-700">DEFAULT</span>@endif
                </td>
                <td>{{ $plan->isFree() ? 'Free' : money($plan->price, 0) }}</td>
                <td>{{ $plan->duration_days > 0 ? $plan->duration_days.' din' : 'Lifetime' }}</td>
                <td class="whitespace-nowrap">{{ $plan->dailyVideos() }} <span class="text-xs text-slate-400">({{ $plan->daily_cycle_limit }}×{{ $plan->videosPerCycle() }}, {{ $plan->adsPerCycle() }} ads)</span></td>
                <td>{{ $plan->multiplierLabel() }}</td>
                <td>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $plan->withdrawals_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $plan->withdrawals_enabled ? 'Allowed' : 'Nahi' }}</span>
                </td>
                <td class="whitespace-nowrap">{{ money($plan->min_withdrawal, 0) }} – {{ (float) $plan->max_withdrawal > 0 ? money($plan->max_withdrawal, 0) : '∞' }}</td>
                <td>{{ $plan->required_referrals ?: '—' }}</td>
                <td>{{ $plan->withdrawal_gap_days ? $plan->withdrawal_gap_days.' din' : '—' }}</td>
                <td>{{ $plan->active_users }}</td>
                <td class="whitespace-nowrap text-right">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="a-btn-light !px-2.5 !py-1.5 text-xs">Edit</a>
                    <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('Plan delete karein?')">@csrf @method('DELETE')
                        <button class="a-btn-light !px-2.5 !py-1.5 text-xs text-rose-600">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="mt-3 text-xs text-slate-500">"Default" plan har naye user ko milta hai, aur paid plan expire hone par user isi par wapas aa jata hai. Chhota plan = zyada shartein, bara plan = kam shartein.</p>
@endsection
