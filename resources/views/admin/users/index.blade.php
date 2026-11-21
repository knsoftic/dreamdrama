@extends('layouts.admin')
@section('title', 'Users')

@section('content')
<form class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Naam, phone, email ya IP" class="a-input max-w-xs">
    <select name="filter" class="a-input !w-auto">
        <option value="">Sab users</option>
        <option value="flagged" @selected($filter === 'flagged')>Flagged</option>
        <option value="blocked" @selected($filter === 'blocked')>Blocked</option>
    </select>
    <button class="a-btn-light">Filter</button>
</form>

<div class="a-card overflow-x-auto">
    <table class="a-table">
        <thead><tr><th>User</th><th>Phone</th><th>Balance</th><th>Total kamaya</th><th>Last IP</th><th>Last active</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            <tr>
                <td>
                    <a href="{{ route('admin.users.show', $u) }}" class="font-semibold text-slate-900 hover:text-violet-600">{{ $u->name }}</a>
                    @if ($u->is_flagged)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">FLAG</span>@endif
                    <div class="text-xs text-slate-500">{{ $u->email }}</div>
                </td>
                <td>{{ $u->phone }}</td>
                <td class="font-semibold">{{ money($u->balance) }}</td>
                <td>{{ money($u->total_earned) }}</td>
                <td class="font-mono text-xs">{{ $u->last_ip }}</td>
                <td>{{ $u->last_active_at?->diffForHumans() ?? '—' }}</td>
                <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $u->isBlocked() ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ ucfirst($u->status) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-10 text-center text-slate-500">Koi user nahi mila</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $users->links() }}</div>
@endsection
