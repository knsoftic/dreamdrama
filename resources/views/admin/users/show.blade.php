@extends('layouts.admin')
@section('title', $user->name)
@section('actions')
    <form method="POST" action="{{ route('admin.users.flag', $user) }}">@csrf
        <button class="a-btn-light"><x-icon name="flag" class="size-4"/> {{ $user->is_flagged ? 'Unflag' : 'Flag' }}</button>
    </form>
    @unless ($user->isAdmin())
        <form method="POST" action="{{ route('admin.users.block', $user) }}" onsubmit="return confirm('{{ $user->isBlocked() ? 'Unblock' : 'Block' }} this user?')">@csrf
            <button class="{{ $user->isBlocked() ? 'a-btn-success' : 'a-btn-danger' }}">{{ $user->isBlocked() ? 'Unblock' : 'Block' }}</button>
        </form>
    @endunless
@endsection

@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6">
        <div class="a-card p-5">
            <div class="flex items-center gap-3">
                <div class="grid size-12 place-items-center rounded-xl bg-violet-100 font-bold text-violet-700">{{ $user->initials() }}</div>
                <div>
                    <div class="font-bold text-slate-900">{{ $user->name }}
                        @if ($user->is_flagged)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">FLAG</span>@endif
                    </div>
                    <div class="text-sm text-slate-500">{{ $user->phone }} {{ $user->email ? '· '.$user->email : '' }}</div>
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-xs text-slate-500">Balance</dt><dd class="font-bold text-slate-900">{{ money($user->balance, 3) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Total earned</dt><dd class="font-bold text-slate-900">{{ money($user->total_earned) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Withdraw kiya</dt><dd class="font-semibold">{{ money($user->total_withdrawn) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Plan</dt><dd class="font-semibold">{{ $plan->name }} {{ $subscription?->ends_at ? '(till '.$subscription->ends_at->format('d M').')' : '' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Cycles today</dt><dd class="font-semibold">{{ $state['cycles_today'] }}/{{ $state['cycle_limit'] }}</dd></div>
                <div><dt class="text-xs text-slate-500">Join</dt><dd class="font-semibold">{{ $user->created_at->format('d M Y') }}</dd></div>
                <div><dt class="text-xs text-slate-500">Register IP</dt><dd class="font-mono text-xs">{{ $user->register_ip }}</dd></div>
                <div><dt class="text-xs text-slate-500">Last IP</dt><dd class="font-mono text-xs">{{ $user->last_ip }}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-slate-500">Device ID</dt><dd class="truncate font-mono text-xs">{{ $user->device_id ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Invite code</dt><dd class="font-mono font-semibold">{{ $user->referral_code }}</dd></div>
                <div><dt class="text-xs text-slate-500">Invites (active / total)</dt><dd class="font-semibold">{{ $referralsActive }} / {{ $referralsTotal }}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-slate-500">Kis ne invite kiya</dt>
                    <dd class="font-semibold">@if ($referrer)<a href="{{ route('admin.users.show', $referrer) }}" class="text-violet-600 hover:underline">{{ $referrer->name }} ({{ $referrer->phone }})</a>@else — @endif</dd></div>
            </dl>
        </div>

        <div class="a-card p-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900">Withdrawal eligibility</h3>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $withdrawRules['allowed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $withdrawRules['allowed'] ? 'Eligible' : 'Not yet' }}</span>
            </div>
            <ul class="mt-3 space-y-1.5 text-sm">
                @foreach ($withdrawRules['checks'] as $check)
                    <li class="flex items-start gap-2 {{ $check['ok'] ? 'text-slate-700' : 'text-rose-700' }}">
                        <x-icon :name="$check['ok'] ? 'check' : 'x'" class="mt-0.5 size-4 shrink-0" stroke="2.5"/>
                        <span>{{ $check['label'] }}@if ($check['detail']) <span class="text-slate-500">— {{ $check['detail'] }}</span>@endif</span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('admin.users.withdraw-exempt', $user) }}" class="mt-3 flex items-start gap-2 border-t border-slate-100 pt-3">@csrf
                <input type="checkbox" id="we{{ $user->id }}" onchange="this.form.submit()" @checked($user->withdraw_exempt) class="mt-0.5 size-4 accent-violet-600">
                <label for="we{{ $user->id }}" class="cursor-pointer text-sm">
                    <span class="font-semibold text-slate-800">Direct withdrawal</span>
                    <span class="text-slate-500">— saara criteria bypass; user ko "criteria complete" dikhega.</span>
                </label>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.users.balance', $user) }}" class="a-card space-y-3 p-5">
            @csrf
            <h3 class="font-bold text-slate-900">Balance adjust</h3>
            <div class="grid grid-cols-2 gap-2">
                <select name="type" class="a-input"><option value="credit">+ Credit</option><option value="debit">- Debit</option></select>
                <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount" class="a-input" required>
            </div>
            <input name="description" placeholder="Wajah (user ko dikhegi)" class="a-input" required>
            <button class="a-btn-primary w-full">Apply</button>
        </form>

        <form method="POST" action="{{ route('admin.users.plan', $user) }}" class="a-card space-y-3 p-5">
            @csrf
            <h3 class="font-bold text-slate-900">Plan change</h3>
            <select name="plan_id" class="a-input">
                @foreach ($plans as $p)<option value="{{ $p->id }}" @selected($p->id === $plan->id)>{{ $p->name }} ({{ $p->isFree() ? 'Free' : money($p->price, 0) }})</option>@endforeach
            </select>
            <input name="days" type="number" min="0" placeholder="Days (empty = plan default duration, 0 = lifetime)" class="a-input">
            <button class="a-btn-primary w-full">Set plan</button>
        </form>
    </div>

    <div class="space-y-6 xl:col-span-2">
        @if ($sameIp->isNotEmpty() || $fraudLogs->isNotEmpty())
            <div class="a-card border-amber-200 p-5">
                <h3 class="flex items-center gap-2 font-bold text-amber-800"><x-icon name="shield" class="size-5"/> Fraud signals</h3>
                @if ($sameIp->isNotEmpty())
                    <p class="mt-2 text-sm text-slate-600">Same IP wale doosre accounts:</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($sameIp as $o)
                            <a href="{{ route('admin.users.show', $o) }}" class="rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">{{ $o->name }} · {{ $o->phone }}</a>
                        @endforeach
                    </div>
                @endif
                @foreach ($fraudLogs as $log)
                    <div class="mt-2 text-xs text-slate-600">{{ $log->created_at->format('d M, h:i A') }} — {{ \App\Models\FraudLog::TYPES[$log->type] ?? $log->type }}: {{ $log->details }}</div>
                @endforeach
            </div>
        @endif

        <div class="a-card overflow-x-auto">
            <div class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Cycles</div>
            <table class="a-table">
                <thead><tr><th>#</th><th>Videos</th><th>Ads</th><th>Seconds</th><th>Earning</th><th>Credited</th><th>Status</th><th>Waqt</th></tr></thead>
                <tbody>
                @forelse ($cycles as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td>{{ $c->videos_watched }}/{{ $c->videos_required }}</td>
                        <td>{{ $c->ads_watched }}/{{ $c->ads_required }}</td>
                        <td>{{ $c->video_seconds }}</td>
                        <td>{{ money($c->total_earning, 3) }}</td>
                        <td>{{ money($c->credited_amount, 3) }}</td>
                        <td>{{ $c->status === 'completed' ? 'Complete' : 'In progress' }}</td>
                        <td class="text-xs">{{ ($c->completed_at ?? $c->created_at)->format('d M, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-slate-500">No cycles</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="a-card overflow-x-auto">
            <div class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Wallet transactions</div>
            <table class="a-table">
                <thead><tr><th>Waqt</th><th>Detail</th><th>Amount</th><th>Balance</th></tr></thead>
                <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td class="text-xs">{{ $t->created_at->format('d M, h:i A') }}</td>
                        <td>{{ $t->description ?: $t->sourceLabel() }}</td>
                        <td class="font-semibold {{ $t->type === 'credit' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $t->type === 'credit' ? '+' : '-' }}{{ money($t->amount, 3) }}</td>
                        <td>{{ money($t->balance_after, 3) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-500">No transactions</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
