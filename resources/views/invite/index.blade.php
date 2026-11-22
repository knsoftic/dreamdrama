@extends('layouts.app')
@section('title', 'Invite')
@section('subtitle', 'Invite friends — meet the withdrawal requirement')

@php
    $required = (int) $plan->required_referrals;
    $link = $user->inviteLink();
    $shareText = setting('site_name').' — watch short videos and earn! Join with my link: '.$link;
    $maskPhone = fn (?string $p) => $p ? substr($p, 0, 4).'****'.substr($p, -3) : '';
@endphp

@section('content')
<div x-data="{ copied: '' , copy(text, what) { navigator.clipboard?.writeText(text); this.copied = what; setTimeout(() => this.copied = '', 1800); } }"
     class="space-y-4 lg:grid lg:grid-cols-3 lg:gap-6 lg:space-y-0">

    <div class="grad relative overflow-hidden rounded-3xl p-5 shadow-lg shadow-brand/20 lg:col-span-2">
        <div class="absolute -right-10 -top-10 size-40 rounded-full bg-white/10"></div>
        <div class="relative flex items-center gap-2 text-sm font-semibold text-white/85"><x-icon name="users" class="size-5"/> Your invites</div>
        <div class="relative mt-2 flex items-end gap-6">
            <div><div class="text-4xl font-extrabold">{{ $active }}</div><div class="text-xs text-white/75">Active</div></div>
            <div><div class="text-2xl font-bold text-white/90">{{ $total }}</div><div class="text-xs text-white/75">Total joined</div></div>
            @if ($required > 0)
                <div><div class="text-2xl font-bold text-white/90">{{ $required }}</div><div class="text-xs text-white/75">For withdrawal</div></div>
            @endif
        </div>
        @if ($required > 0)
            <div class="relative mt-4 h-2 overflow-hidden rounded-full bg-black/20">
                <div class="h-full rounded-full bg-white" style="width: {{ min(100, round(100 * $active / $required)) }}%"></div>
            </div>
            <p class="relative mt-2 text-xs text-white/85">
                {{ $active >= $required ? 'Invite requirement met ✓' : 'You need '.($required - $active).' more active invites to withdraw ('.$plan->name.' plan).' }}
            </p>
        @endif
    </div>

    <div class="card p-5 text-sm lg:row-span-2 lg:self-start">
        <h3 class="font-bold">When does an invite count as "active"?</h3>
        <ol class="mt-3 space-y-2 text-app-muted">
            <li class="flex gap-2"><span class="font-bold text-brand">1.</span> A friend registers with your link or code</li>
            @if ($minCycles > 0)
                <li class="flex gap-2"><span class="font-bold text-brand">2.</span> They complete at least {{ $minCycles }} {{ $minCycles === 1 ? 'cycle' : 'cycles' }} (videos + ads)</li>
            @endif
            <li class="flex gap-2"><span class="font-bold text-brand">{{ $minCycles > 0 ? 3 : 2 }}.</span> The account is not blocked or flagged</li>
        </ol>
        <p class="mt-3 text-xs text-app-muted">Fake / duplicate accounts do not count and your account may get flagged.</p>
    </div>

    <div class="card space-y-4 p-5 lg:col-span-2">
        <div>
            <div class="app-label">Your invite code</div>
            <div class="flex gap-2">
                <div class="app-input flex-1 text-center font-mono text-xl font-bold tracking-[0.3em]">{{ $user->referral_code }}</div>
                <button @click="copy(@js($user->referral_code), 'code')" class="btn-ghost !px-4" x-text="copied === 'code' ? 'Copied ✓' : 'Copy'"></button>
            </div>
        </div>
        <div>
            <div class="app-label">Invite link</div>
            <div class="flex gap-2">
                <div class="app-input min-w-0 flex-1 truncate text-sm">{{ $link }}</div>
                <button @click="copy(@js($link), 'link')" class="btn-ghost !px-4" x-text="copied === 'link' ? 'Copied ✓' : 'Copy'"></button>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener" class="btn !bg-emerald-500 text-white hover:brightness-110">
                <x-icon name="chat" class="size-5"/> WhatsApp
            </a>
            <button @click="navigator.share ? navigator.share({ text: @js($shareText) }) : copy(@js($shareText), 'link')" class="btn-primary">
                <x-icon name="share" class="size-5"/> Share
            </button>
        </div>
    </div>

    {{-- 3-level downline (MLM) --}}
    <div class="card p-5 lg:col-span-3">
        <h3 class="font-bold">Your downline (3 levels)</h3>
        <p class="text-xs text-app-muted">People you invited, and the people they invite.</p>
        <div class="mt-4 grid grid-cols-3 gap-3">
            @foreach ([1 => 'Level 1', 2 => 'Level 2', 3 => 'Level 3'] as $lvl => $label)
                <div class="rounded-2xl bg-app-card2 p-4 text-center">
                    <div class="text-2xl font-extrabold text-brand">{{ number_format($levels[$lvl]) }}</div>
                    <div class="text-[11px] text-app-muted">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card overflow-hidden lg:col-span-3">
        <div class="border-b border-app-line px-5 py-4 font-bold">Friends who joined</div>
        <div class="divide-y divide-app-line">
            @forelse ($referrals as $r)
                @php
                    $counted = $r->status === 'active' && ! $r->is_flagged && $r->completed_cycles >= $minCycles;
                @endphp
                <div class="flex items-center gap-3 px-5 py-3.5">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-app-card2 text-sm font-bold">{{ $r->initials() }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ \Illuminate\Support\Str::of($r->name)->explode(' ')->first() }} <span class="font-normal text-app-muted">{{ $maskPhone($r->phone) }}</span></div>
                        <div class="text-xs text-app-muted">Joined {{ $r->created_at->diffForHumans() }} · {{ $r->completed_cycles }} cycles</div>
                    </div>
                    @if ($counted)
                        <span class="chip bg-emerald-500/15 text-emerald-300">Active</span>
                    @elseif ($r->status !== 'active' || $r->is_flagged)
                        <span class="chip bg-rose-500/15 text-rose-300">Doesn't count</span>
                    @else
                        <span class="chip bg-amber-500/15 text-amber-300">{{ $r->completed_cycles }}/{{ $minCycles }} cycle</span>
                    @endif
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-app-muted">No friends have joined yet. Share your link!</div>
            @endforelse
        </div>
        @if ($referrals->hasPages())
            <div class="border-t border-app-line px-5 py-3">{{ $referrals->links('partials.pager') }}</div>
        @endif
    </div>
</div>
@endsection
