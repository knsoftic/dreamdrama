@extends('layouts.app')
@section('title', 'Profile')
@section('subtitle', 'Account aur settings')

@section('content')
<div x-data="{ tab: '{{ $errors->has('current_password') || $errors->has('password') ? 'password' : 'info' }}' }" class="mx-auto max-w-2xl space-y-4">
    <div class="card flex items-center gap-4 p-5">
        <div class="grad grid size-16 shrink-0 place-items-center rounded-2xl text-xl font-extrabold shadow-lg shadow-brand/30">{{ $user->initials() }}</div>
        <div class="min-w-0 flex-1">
            <div class="truncate text-lg font-bold">{{ $user->name }}</div>
            <div class="text-sm text-app-muted">{{ $user->phone }}</div>
            <span class="chip mt-1.5 bg-coin/15 text-coin"><x-icon name="crown" class="size-3.5"/> {{ $plan->name }}</span>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-3 text-center">
        <div class="card p-3"><div class="text-lg font-bold">{{ money($user->balance, 0) }}</div><div class="text-[11px] text-app-muted">Balance</div></div>
        <div class="card p-3"><div class="text-lg font-bold">{{ money($user->total_earned, 0) }}</div><div class="text-[11px] text-app-muted">Total kamaya</div></div>
        <div class="card p-3"><div class="text-lg font-bold">{{ $state['cycles_today'] }}/{{ $state['cycle_limit'] }}</div><div class="text-[11px] text-app-muted">Cycles aaj</div></div>
    </div>

    <div class="card divide-y divide-app-line overflow-hidden">
        <a href="{{ route('plans') }}" class="flex items-center gap-3 px-5 py-4 active:bg-white/5">
            <x-icon name="crown" class="size-5 text-coin"/><span class="flex-1 text-sm font-semibold">Mera plan</span>
            <span class="text-xs text-app-muted">{{ $subscription?->ends_at ? 'Expire '.$subscription->ends_at->format('d M') : $plan->name }}</span>
            <x-icon name="chevron-right" class="size-4 text-app-muted"/>
        </a>
        <a href="{{ route('wallet') }}" class="flex items-center gap-3 px-5 py-4 active:bg-white/5">
            <x-icon name="wallet" class="size-5 text-brand"/><span class="flex-1 text-sm font-semibold">Wallet & withdraw</span>
            <x-icon name="chevron-right" class="size-4 text-app-muted"/>
        </a>
        <a href="{{ route('earnings') }}" class="flex items-center gap-3 px-5 py-4 active:bg-white/5">
            <x-icon name="chart" class="size-5 text-brand-2"/><span class="flex-1 text-sm font-semibold">Earning history</span>
            <x-icon name="chevron-right" class="size-4 text-app-muted"/>
        </a>
        @if (setting('support_whatsapp'))
            <a href="https://wa.me/{{ preg_replace('/\D/', '', setting('support_whatsapp')) }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-5 py-4 active:bg-white/5">
                <x-icon name="chat" class="size-5 text-emerald-400"/><span class="flex-1 text-sm font-semibold">Support (WhatsApp)</span>
                <x-icon name="chevron-right" class="size-4 text-app-muted"/>
            </a>
        @endif
        @if ($user->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-5 py-4 active:bg-white/5">
                <x-icon name="settings" class="size-5 text-amber-300"/><span class="flex-1 text-sm font-semibold">Admin panel</span>
                <x-icon name="chevron-right" class="size-4 text-app-muted"/>
            </a>
        @endif
    </div>

    <div class="card p-5">
        <div class="mb-4 flex rounded-xl bg-app-card2 p-1 text-sm font-semibold">
            <button @click="tab = 'info'" class="flex-1 rounded-lg py-2 transition" :class="tab === 'info' ? 'bg-white/10 text-white' : 'text-app-muted'">Profile</button>
            <button @click="tab = 'password'" class="flex-1 rounded-lg py-2 transition" :class="tab === 'password' ? 'bg-white/10 text-white' : 'text-app-muted'">Password</button>
        </div>

        <form x-show="tab === 'info'" method="POST" action="{{ route('profile.update') }}" class="space-y-3">
            @csrf @method('PUT')
            <div><label class="app-label">Naam</label><input name="name" value="{{ old('name', $user->name) }}" class="app-input" required></div>
            <div><label class="app-label">Phone</label><input value="{{ $user->phone }}" class="app-input opacity-60" disabled></div>
            <div><label class="app-label">Email <span class="text-app-muted">(optional)</span></label><input name="email" type="email" value="{{ old('email', $user->email) }}" class="app-input">
                @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
            <button class="btn-primary w-full">Save</button>
        </form>

        <form x-show="tab === 'password'" x-cloak method="POST" action="{{ route('profile.password') }}" class="space-y-3">
            @csrf @method('PUT')
            <div><label class="app-label">Purana password</label><input name="current_password" type="password" class="app-input" required autocomplete="current-password">
                @error('current_password')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
            <div><label class="app-label">Naya password</label><input name="password" type="password" class="app-input" required autocomplete="new-password">
                @error('password')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror</div>
            <div><label class="app-label">Naya password dobara</label><input name="password_confirmation" type="password" class="app-input" required autocomplete="new-password"></div>
            <button class="btn-primary w-full">Password change karein</button>
        </form>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
        @csrf
        <button class="btn-ghost w-full text-rose-300"><x-icon name="logout" class="size-5"/> Logout</button>
    </form>
</div>
@endsection
