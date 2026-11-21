@extends('layouts.app')
@section('title', 'Invite')
@section('subtitle', 'Dost invite karein — withdrawal ki shart poori karein')

@php
    $required = (int) $plan->required_referrals;
    $link = $user->inviteLink();
    $shareText = setting('site_name').' par short videos dekh kar kamaayein! Mere link se join karein: '.$link;
    $maskPhone = fn (?string $p) => $p ? substr($p, 0, 4).'****'.substr($p, -3) : '';
@endphp

@section('content')
<div x-data="{ copied: '' , copy(text, what) { navigator.clipboard?.writeText(text); this.copied = what; setTimeout(() => this.copied = '', 1800); } }"
     class="space-y-4 lg:grid lg:grid-cols-3 lg:gap-6 lg:space-y-0">

    <div class="grad relative overflow-hidden rounded-3xl p-5 shadow-lg shadow-brand/20 lg:col-span-2">
        <div class="absolute -right-10 -top-10 size-40 rounded-full bg-white/10"></div>
        <div class="relative flex items-center gap-2 text-sm font-semibold text-white/85"><x-icon name="users" class="size-5"/> Aap ke invites</div>
        <div class="relative mt-2 flex items-end gap-6">
            <div><div class="text-4xl font-extrabold">{{ $active }}</div><div class="text-xs text-white/75">Active</div></div>
            <div><div class="text-2xl font-bold text-white/90">{{ $total }}</div><div class="text-xs text-white/75">Total joined</div></div>
            @if ($required > 0)
                <div><div class="text-2xl font-bold text-white/90">{{ $required }}</div><div class="text-xs text-white/75">Withdrawal ke liye</div></div>
            @endif
        </div>
        @if ($required > 0)
            <div class="relative mt-4 h-2 overflow-hidden rounded-full bg-black/20">
                <div class="h-full rounded-full bg-white" style="width: {{ min(100, round(100 * $active / $required)) }}%"></div>
            </div>
            <p class="relative mt-2 text-xs text-white/85">
                {{ $active >= $required ? 'Invite ki shart poori ho gayi ✓' : 'Withdrawal ke liye '.($required - $active).' aur active invites chahiye ('.$plan->name.' plan).' }}
            </p>
        @endif
    </div>

    <div class="card p-5 text-sm lg:row-span-2 lg:self-start">
        <h3 class="font-bold">Invite kab "active" ginta hai?</h3>
        <ol class="mt-3 space-y-2 text-app-muted">
            <li class="flex gap-2"><span class="font-bold text-brand">1.</span> Dost aap ke link ya code se register kare</li>
            @if ($minCycles > 0)
                <li class="flex gap-2"><span class="font-bold text-brand">2.</span> Woh kam az kam {{ $minCycles }} {{ $minCycles === 1 ? 'cycle' : 'cycles' }} poori kare (videos + ads)</li>
            @endif
            <li class="flex gap-2"><span class="font-bold text-brand">{{ $minCycles > 0 ? 3 : 2 }}.</span> Account block ya flag na ho</li>
        </ol>
        <p class="mt-3 text-xs text-app-muted">Fake / doosre accounts count nahi hote aur aap ka account flag ho sakta hai.</p>
    </div>

    <div class="card space-y-4 p-5 lg:col-span-2">
        <div>
            <div class="app-label">Aap ka invite code</div>
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

    <div class="card overflow-hidden lg:col-span-3">
        <div class="border-b border-app-line px-5 py-4 font-bold">Join karne wale dost</div>
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
                        <span class="chip bg-rose-500/15 text-rose-300">Count nahi</span>
                    @else
                        <span class="chip bg-amber-500/15 text-amber-300">{{ $r->completed_cycles }}/{{ $minCycles }} cycle</span>
                    @endif
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-app-muted">Abhi tak koi dost join nahi hua. Link share karein!</div>
            @endforelse
        </div>
        @if ($referrals->hasPages())
            <div class="border-t border-app-line px-5 py-3">{{ $referrals->links('partials.pager') }}</div>
        @endif
    </div>
</div>
@endsection
