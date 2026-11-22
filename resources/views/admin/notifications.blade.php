@extends('layouts.admin')
@section('title', 'Notifications')

@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">

    {{-- Send --}}
    <div class="a-card self-start p-5">
        <h2 class="font-bold text-slate-900">Send notification</h2>
        <p class="mt-1 text-xs text-slate-500">{{ $devices }} devices · {{ $users }} users have allowed notifications in the app.</p>

        @unless ($configured)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Save the service account (step 1 on the right) first, then you can send notifications.
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
                    <option value="all">All devices</option>
                    <option value="active">Only active (not blocked/flagged) users</option>
                </select>
            </div>
            <button class="a-btn-primary w-full" {{ $configured ? '' : 'disabled' }}>
                <x-icon name="bolt" class="size-4"/> Send notification
            </button>
        </form>
    </div>

    <div class="space-y-6 self-start">
        {{-- Step 1: service account (sending) --}}
        <div class="a-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-slate-900">1. Service account <span class="font-normal text-slate-400">(to send)</span></h2>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $configured ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $configured ? 'Connected'.($projectId ? ' · '.$projectId : '') : 'Not set' }}
                </span>
            </div>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-xs text-slate-600">
                <li><a href="https://console.firebase.google.com" target="_blank" class="text-violet-600 underline">Firebase Console</a> → create a project (or open yours).</li>
                <li>Project settings → <strong>Service accounts</strong> → <strong>Generate new private key</strong> → download the JSON.</li>
                <li>Paste that JSON below and Save.</li>
            </ol>
            <form method="POST" action="{{ route('admin.notifications.config') }}" class="mt-4 space-y-3">
                @csrf
                <textarea name="fcm_service_account" rows="5" class="a-input font-mono text-xs" placeholder='{ "type": "service_account", "project_id": "...", ... }'></textarea>
                @if ($hasAccount)<p class="text-xs text-emerald-600">Saved. Paste new JSON to change it, or Save empty to remove.</p>@endif
                @error('fcm_service_account')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                <button class="a-btn-light">Save service account</button>
            </form>
        </div>

        {{-- Step 2: google-services.json (receiving / APK) --}}
        <div class="a-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-slate-900">2. google-services.json <span class="font-normal text-slate-400">(for the APK)</span></h2>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $hasGoogleServices ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $hasGoogleServices ? 'Uploaded' : 'Not set' }}
                </span>
            </div>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-xs text-slate-600">
                <li>In Firebase → <strong>Add app → Android</strong>, package name <code class="rounded bg-slate-100 px-1">site.dramaearn.app</code>.</li>
                <li>Download <strong>google-services.json</strong> and upload it here.</li>
                <li>Then <strong>rebuild the APK</strong> (GitHub → Actions → Build Android APK). The build downloads this file automatically.</li>
            </ol>
            <form method="POST" action="{{ route('admin.notifications.google-services') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <input type="file" name="google_services_file" accept="application/json,.json" class="block w-full text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                <p class="text-[11px] text-slate-400">Or paste the JSON:</p>
                <textarea name="google_services_json" rows="3" class="a-input font-mono text-xs" placeholder='{ "project_info": {...}, "client": [...] }'></textarea>
                @if ($hasGoogleServices)
                    <p class="text-xs text-emerald-600">Uploaded · served at <a href="{{ $googleServicesUrl }}" target="_blank" class="underline">/google-services.json</a>. Upload again to replace, or Save empty to remove.</p>
                @endif
                @error('google_services_json')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                @error('google_services_file')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                <button class="a-btn-light">Save google-services.json</button>
            </form>
        </div>

        <p class="text-xs text-slate-500">Push works only in a <strong>new APK built after</strong> uploading google-services.json. Users must install that new APK.</p>
    </div>
</div>
@endsection
