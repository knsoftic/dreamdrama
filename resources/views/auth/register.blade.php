@extends('layouts.guest')
@section('title', 'Register')

@section('content')
<h1 class="text-3xl font-extrabold tracking-tight">Create account</h1>
<p class="mt-2 text-app-muted">Free account — watch videos, watch ads, and earn.</p>

@if (setting('registration_open', '1') !== '1')
    <div class="mt-8 rounded-xl bg-amber-500/10 px-4 py-3 text-sm text-amber-200">New accounts cannot be created right now. Please try again later.</div>
@else
<form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
    @csrf
    <div>
        <label class="app-label">Full name</label>
        <input name="name" value="{{ old('name') }}" class="app-input" required autofocus autocomplete="name">
        @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="app-label">Mobile number</label>
        <input name="phone" value="{{ old('phone') }}" class="app-input" required inputmode="tel" autocomplete="tel" placeholder="03XX XXXXXXX">
        @error('phone')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="app-label">Email <span class="text-app-muted">(optional)</span></label>
        <input name="email" type="email" value="{{ old('email') }}" class="app-input" autocomplete="email">
        @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="app-label">Password</label>
            <input name="password" type="password" class="app-input" required autocomplete="new-password">
        </div>
        <div>
            <label class="app-label">Repeat</label>
            <input name="password_confirmation" type="password" class="app-input" required autocomplete="new-password">
        </div>
    </div>
    @error('password')<p class="text-xs text-rose-400">{{ $message }}</p>@enderror
    <div>
        <label class="app-label">Invite code <span class="text-app-muted">(optional)</span></label>
        <input name="ref" value="{{ old('ref', $ref) }}" class="app-input font-mono uppercase tracking-widest" maxlength="12" autocomplete="off" placeholder="ABC1234">
        @if ($inviter && ! $errors->has('ref'))<p class="mt-1 text-xs text-emerald-400">{{ $inviter }} invited you ✓</p>@endif
        @error('ref')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </div>
    <button class="btn-primary w-full !py-3.5 text-base">Create account</button>
    <p class="text-center text-xs text-app-muted">Only one account is allowed per device / number.</p>
</form>
@endif

<p class="mt-auto pt-10 text-center text-sm text-app-muted lg:mt-8 lg:pt-0">
    Already have an account? <a href="{{ route('login') }}" class="font-semibold text-white underline decoration-brand underline-offset-4">Login</a>
</p>
@endsection
