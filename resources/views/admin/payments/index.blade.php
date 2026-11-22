@extends('layouts.admin')
@section('title', 'Subscription Payments')

@section('content')
@include('admin.partials.status-tabs', ['options' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'Sab']])

<div class="space-y-3">
    @forelse ($payments as $p)
        <div class="a-card flex flex-col gap-4 p-4 lg:flex-row lg:items-center">
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.users.show', $p->user) }}" class="font-bold text-slate-900 hover:text-violet-600">{{ $p->user->name }}</a>
                    <span class="text-sm text-slate-500">{{ $p->user->phone }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ['pending' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-rose-100 text-rose-700'][$p->status] }}">{{ ucfirst($p->status) }}</span>
                    @if ($p->isGateway())
                        <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-700">Gateway{{ $p->gateway_status ? ': '.$p->gateway_status : '' }}</span>
                    @endif
                </div>
                <div class="mt-1 text-sm text-slate-700">
                    <strong>{{ $p->plan->name }}</strong> · {{ money($p->amount) }} · {{ $p->method }} ·
                    TID <span class="font-mono font-semibold">{{ $p->transaction_id }}</span>
                    @if ($p->sender_number) · From {{ $p->sender_number }} @endif
                </div>
                <div class="mt-0.5 text-xs text-slate-500">{{ $p->created_at->format('d M Y, h:i A') }} @if ($p->admin_note) · Note: {{ $p->admin_note }} @endif</div>
            </div>
            @if ($p->screenshot_path)
                <a href="{{ Storage::url($p->screenshot_path) }}" target="_blank" class="a-btn-light text-xs">Screenshot</a>
            @endif
            @if ($p->status === 'pending')
                <div class="flex flex-wrap gap-2">
                    @if ($p->isGateway() && $p->gateway_payment_id)
                        <form method="POST" action="{{ route('admin.payments.check', $p) }}">@csrf
                            <button class="a-btn-light"><x-icon name="bolt" class="size-4"/> Check status</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.payments.approve', $p) }}" onsubmit="return confirm('Approve the payment and activate the plan?')">@csrf
                        <button class="a-btn-success"><x-icon name="check" class="size-4" stroke="2.5"/> Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.payments.reject', $p) }}" class="flex gap-2">@csrf
                        <input name="admin_note" placeholder="Reason for rejection" class="a-input !w-40 !py-1.5 text-sm">
                        <button class="a-btn-danger">Reject</button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <div class="a-card p-10 text-center text-slate-500">No payments in this list.</div>
    @endforelse
</div>
<div class="mt-6">{{ $payments->links() }}</div>
@endsection
