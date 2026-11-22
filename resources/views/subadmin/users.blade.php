@extends('layouts.panel')
@section('title', 'My Users')

@section('content')
<form class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Name, phone or email" class="a-input max-w-xs">
    <select name="plan" class="a-input !w-auto">
        <option value="">All plans</option>
        <option value="free" @selected($plan === 'free')>Free</option>
        <option value="paid" @selected($plan === 'paid')>Paid</option>
    </select>
    <select name="status" class="a-input !w-auto">
        <option value="">Any status</option>
        <option value="active" @selected($status === 'active')>Active</option>
        <option value="blocked" @selected($status === 'blocked')>Blocked</option>
    </select>
    <button class="a-btn-light">Filter</button>
</form>

<div class="a-card overflow-x-auto">
    <table class="a-table">
        <thead><tr><th>User</th><th>Phone</th><th>Plan</th><th>Invited by</th><th>Joined</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            @php $p = $u->currentPlan(); @endphp
            <tr>
                <td class="font-semibold text-slate-900">{{ $u->name }}<div class="text-xs font-normal text-slate-500">{{ $u->email }}</div></td>
                <td>{{ $u->phone }}</td>
                <td><span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full" style="background: {{ $p->color }}"></span>{{ $p->name }}</span></td>
                <td>{{ $u->referrer?->name ?? '—' }}</td>
                <td class="whitespace-nowrap text-xs">{{ $u->created_at->format('d M Y') }}</td>
                <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $u->isBlocked() ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ ucfirst($u->status) }}</span></td>
                <td class="text-right">
                    <form method="POST" action="{{ route('team.users.block', $u) }}" onsubmit="return confirm('{{ $u->isBlocked() ? 'Unblock' : 'Block' }} this user?')">@csrf
                        <button class="a-btn-light !px-2.5 !py-1.5 text-xs {{ $u->isBlocked() ? 'text-emerald-700' : 'text-rose-600' }}">{{ $u->isBlocked() ? 'Unblock' : 'Block' }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-10 text-center text-slate-500">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $users->links() }}</div>
@endsection
