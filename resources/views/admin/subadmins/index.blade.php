@extends('layouts.admin')
@section('title', 'Sub Admins')
@section('actions')
    <a href="{{ route('admin.subadmins.create') }}" class="a-btn-primary"><x-icon name="plus" class="size-4" stroke="2.5"/> New sub-admin</a>
@endsection

@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    {{-- Promote existing user --}}
    <form method="POST" action="{{ route('admin.subadmins.promote') }}" class="a-card space-y-3 self-start p-5">
        @csrf
        <h3 class="font-bold text-slate-900">Promote an existing user</h3>
        <p class="text-xs text-slate-500">Makes a user a sub-admin and moves their existing invitees under them.</p>
        <div><label class="a-label">User phone</label><input name="phone" value="{{ old('phone') }}" class="a-input" required placeholder="03XX XXXXXXX"></div>
        <div><label class="a-label">Special invite code</label><input name="referral_code" value="{{ old('referral_code') }}" class="a-input font-mono uppercase" required placeholder="BOSS123"></div>
        <div><label class="a-label">Platform tax %</label><input name="commission_percent" type="number" step="0.01" min="0" max="100" value="{{ old('commission_percent', 0) }}" class="a-input" required></div>
        <button class="a-btn-primary w-full">Promote to sub-admin</button>
    </form>

    {{-- List --}}
    <div class="xl:col-span-2">
        <form class="mb-4 flex gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Name, phone or code" class="a-input max-w-xs">
            <button class="a-btn-light">Search</button>
        </form>
        <div class="a-card overflow-x-auto">
            <table class="a-table">
                <thead><tr><th>Sub-admin</th><th>Invite code</th><th>Tax %</th><th>Network</th><th>Deposits</th><th>Earned / Balance</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($subAdmins as $sa)
                    <tr x-data="{ edit: false, tax: false, nameEdit: false }">
                        <td>
                            <div x-show="!nameEdit" class="flex items-center gap-2">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $sa->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $sa->phone }}</div>
                                </div>
                                <button type="button" @click="nameEdit = true" class="text-xs text-violet-600">edit</button>
                            </div>
                            <form x-show="nameEdit" x-cloak method="POST" action="{{ route('admin.subadmins.name', $sa) }}" class="flex gap-1">@csrf
                                <input name="name" value="{{ $sa->name }}" maxlength="100" class="a-input !w-36 !py-1 text-xs">
                                <button class="a-btn-primary !px-2 !py-1 text-xs">Save</button>
                            </form>
                        </td>
                        <td>
                            <div x-show="!edit" class="flex items-center gap-2">
                                <span class="font-mono font-bold">{{ $sa->referral_code }}</span>
                                <button type="button" @click="edit = true" class="text-xs text-violet-600">edit</button>
                            </div>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('admin.subadmins.code', $sa) }}" class="flex gap-1">@csrf
                                <input name="referral_code" value="{{ $sa->referral_code }}" class="a-input !w-24 !py-1 font-mono text-xs uppercase">
                                <button class="a-btn-primary !px-2 !py-1 text-xs">Save</button>
                            </form>
                        </td>
                        <td>
                            <div x-show="!tax" class="flex items-center gap-1">
                                <span>{{ rtrim(rtrim(number_format($sa->commission_percent, 2), '0'), '.') }}%</span>
                                <button type="button" @click="tax = true" class="text-xs text-violet-600">edit</button>
                            </div>
                            <form x-show="tax" x-cloak method="POST" action="{{ route('admin.subadmins.commission', $sa) }}" class="flex gap-1">@csrf
                                <input name="commission_percent" type="number" step="0.01" min="0" max="100" value="{{ (float) $sa->commission_percent }}" class="a-input !w-16 !py-1 text-xs">
                                <button class="a-btn-primary !px-2 !py-1 text-xs">Save</button>
                            </form>
                        </td>
                        <td>{{ number_format($sa->network_users_count) }}</td>
                        <td>{{ money($sa->deposits_gross ?? 0, 0) }}</td>
                        <td class="whitespace-nowrap">{{ money($sa->total_earned, 0) }} <span class="text-xs text-slate-400">/ {{ money($sa->balance, 0) }}</span></td>
                        <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $sa->isBlocked() ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ ucfirst($sa->status) }}</span></td>
                        <td class="whitespace-nowrap text-right">
                            <form method="POST" action="{{ route('admin.subadmins.block', $sa) }}" class="inline">@csrf
                                <button class="a-btn-light !px-2 !py-1.5 text-xs">{{ $sa->isBlocked() ? 'Unblock' : 'Block' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.subadmins.demote', $sa) }}" class="inline" onsubmit="return confirm('Make this sub-admin a normal user?')">@csrf
                                <button class="a-btn-light !px-2 !py-1.5 text-xs text-rose-600">Demote</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">No sub-admins yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $subAdmins->links() }}</div>
    </div>
</div>
<p class="mt-4 text-xs text-slate-500">A sub-admin shares their invite code. Everyone who registers with it — and everyone those users invite — becomes part of the sub-admin's network and shows in their panel.</p>
@endsection
