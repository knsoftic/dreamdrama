@extends('layouts.admin')
@section('title', 'Notifications')

@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">

    {{-- Send --}}
    <div class="a-card p-5">
        <h2 class="font-bold text-slate-900">Send notification</h2>
        <p class="mt-1 text-xs text-slate-500">{{ $devices }} devices · {{ $users }} users have allowed notifications in the app.</p>

        @unless ($configured)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Save the Firebase service account below first, then you can send notifications.
            </div>
        @endunless

        <form method="POST" action="{{ route('admin.notifications.send') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="a-label">Title</label>
                <input name="title" value="{{ old('title') }}" class="a-input" maxlength="120" required placeholder="New videos are here! 🎬">
                @error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="a-label">Message</label>
                <textarea name="body" rows="3" class="a-input" maxlength="500" required placeholder="Watch today's new videos and earn more.">{{ old('body') }}</textarea>
                @error('body')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="a-label">Link (optional) — opens this page on tap</label>
                <input name="url" value="{{ old('url', url('/feed')) }}" class="a-input" placeholder="{{ url('/feed') }}">
                @error('url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="a-label">Send to</label>
                <select name="audience" class="a-input">
                    <option value="all">Sab devices</option>
                    <option value="active">Only active (not blocked/flagged) users</option>
                </select>
            </div>
            <button class="a-btn-primary w-full" {{ $configured ? '' : 'disabled' }}>
                <x-icon name="bolt" class="size-4"/> Send notification
            </button>
        </form>
    </div>

    {{-- Firebase config --}}
    <div class="a-card self-start p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-slate-900">Firebase setup</h2>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $configured ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $configured ? 'Connected'.($projectId ? ' · '.$projectId : '') : 'Not set' }}
            </span>
        </div>
        <ol class="mt-3 list-decimal space-y-1 pl-5 text-xs text-slate-600">
            <li><a href="https://console.firebase.google.com" target="_blank" class="text-violet-600 underline">Firebase Console</a> and create a project.</li>
            <li>Project settings → <strong>Service accounts</strong> → <strong>Generate new private key</strong> → JSON download.</li>
            <li>Paste the whole JSON file below and Save.</li>
            <li>For the app, <code>google-services.json</code> is required — see the DEPLOYMENT guide.</li>
        </ol>
        <form method="POST" action="{{ route('admin.notifications.config') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="a-label">Service account JSON</label>
                <textarea name="fcm_service_account" rows="6" class="a-input font-mono text-xs" placeholder='{ "type": "service_account", "project_id": "...", ... }'>{{ old('fcm_service_account', $hasAccount ? '' : '') }}</textarea>
                @if ($hasAccount)<p class="mt-1 text-xs text-emerald-600">Config is saved. Paste new JSON to change it, or Save empty to remove it.</p>@endif
                @error('fcm_service_account')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <button class="a-btn-light">Save config</button>
        </form>
    </div>
</div>
@endsection
