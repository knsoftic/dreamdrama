@extends('layouts.admin')
@section('title', 'Withdrawals')

@section('content')
@include('admin.partials.status-tabs', ['options' => ['pending' => 'Pending', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'Sab']])

<div class="space-y-3">
    @forelse ($withdrawals as $w)
        <div class="a-card flex flex-col gap-4 p-4 lg:flex-row lg:items-center">
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.users.show', $w->user) }}" class="font-bold text-slate-900 hover:text-violet-600">{{ $w->user->name }}</a>
                    @if ($w->user->isSubAdmin())<span class="rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold text-violet-700">SUB-ADMIN</span>@endif
                    @if ($w->user->is_flagged)<span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">FLAGGED</span>@endif
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ['pending' => 'bg-amber-100 text-amber-700', 'paid' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-rose-100 text-rose-700'][$w->status] }}">{{ ucfirst($w->status) }}</span>
                </div>
                <div class="mt-1 text-2xl font-bold text-slate-900">{{ money($w->amount) }}</div>
                <div class="mt-0.5 text-sm text-slate-700">{{ $w->method }} · <strong>{{ $w->account_title }}</strong> · <span class="font-mono">{{ $w->account_number }}</span></div>
                <div class="mt-0.5 text-xs text-slate-500">{{ $w->created_at->format('d M Y, h:i A') }} · Total earned {{ money($w->user->total_earned) }} @if ($w->admin_note) · Note: {{ $w->admin_note }} @endif</div>
            </div>
            @if ($w->status === 'pending')
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.withdrawals.pay', $w) }}" class="flex gap-2" onsubmit="return confirm('Have you sent the payment? Mark as paid?')">@csrf
                        <input name="admin_note" placeholder="TID / note" class="a-input !w-36 !py-1.5 text-sm">
                        <button class="a-btn-success"><x-icon name="check" class="size-4" stroke="2.5"/> Paid</button>
                    </form>
                    <form method="POST" action="{{ route('admin.withdrawals.reject', $w) }}" class="flex gap-2" onsubmit="return confirm('Rejecting returns the amount to the user\'s wallet.')">@csrf
                        <input name="admin_note" placeholder="Wajah" class="a-input !w-32 !py-1.5 text-sm">
                        <button class="a-btn-danger">Reject</button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <div class="a-card p-10 text-center text-slate-500">No withdrawals in this list.</div>
    @endforelse
</div>
<div class="mt-6">{{ $withdrawals->links() }}</div>
@endsection
