@extends('layouts.guest')
@section('title', 'Login')

@section('content')
<h1 class="text-3xl font-extrabold tracking-tight">Welcome back 👋</h1>
<p class="mt-2 text-app-muted">Log in and start earning by watching videos.</p>

<form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
    @csrf
    <div>
        <label class="app-label">Phone number or email</label>
        <input name="login" value="{{ old('login') }}" class="app-input" required autofocus autocomplete="username" placeholder="03XX XXXXXXX">
    </div>
    <div x-data="{ show: false }">
        <label class="app-label">Password</label>
        <div class="relative">
            <input name="password" :type="show ? 'text' : 'password'" class="app-input pr-12" required autocomplete="current-password" placeholder="••••••">
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 grid w-12 place-items-center text-app-muted"><x-icon name="eye" class="size-5"/></button>
        </div>
    </div>
    @error('login')<p class="rounded-xl bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $message }}</p>@enderror
    <button class="btn-primary w-full !py-3.5 text-base">Login</button>
</form>

<p class="mt-auto pt-10 text-center text-sm text-app-muted lg:mt-8 lg:pt-0">
    Don't have an account? <a href="{{ route('register') }}" class="font-semibold text-white underline decoration-brand underline-offset-4">Register</a>
</p>
@endsection
