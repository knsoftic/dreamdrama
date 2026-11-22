@extends('layouts.admin')
@section('title', 'New sub-admin')

@section('content')
<form method="POST" action="{{ route('admin.subadmins.store') }}" class="a-card max-w-xl space-y-4 p-5">
    @csrf
    <p class="text-sm text-slate-500">Create a new sub-admin account. Give them the login and the invite code to share.</p>
    <div>
        <label class="a-label">Name</label>
        <input name="name" value="{{ old('name') }}" class="a-input" required>
        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="a-label">Phone (login)</label>
        <input name="phone" value="{{ old('phone') }}" class="a-input" required placeholder="03XX XXXXXXX">
        @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="a-label">Password</label>
            <input name="password" type="password" class="a-input" required autocomplete="new-password">
            @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="a-label">Repeat password</label>
            <input name="password_confirmation" type="password" class="a-input" required autocomplete="new-password">
        </div>
    </div>
    <div>
        <label class="a-label">Special invite code</label>
        <input name="referral_code" value="{{ old('referral_code') }}" class="a-input font-mono uppercase" required placeholder="BOSS123">
        <p class="mt-1 text-xs text-slate-500">4–12 letters/numbers. Users join under this sub-admin by entering this code.</p>
        @error('referral_code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="flex gap-2">
        <button class="a-btn-primary">Create sub-admin</button>
        <a href="{{ route('admin.subadmins.index') }}" class="a-btn-light">Cancel</a>
    </div>
</form>
@endsection
