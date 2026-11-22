<!DOCTYPE html>
<html lang="en" class="bg-app-bg">
<head>
    @include('partials.head')
    {!! setting('head_scripts') !!}
</head>
<body class="min-h-dvh bg-app-bg font-sans text-white antialiased">
@php
    $steps = [
        ['download', 'Download the app', 'Tap the "Download APK" button below. The .apk file downloads to your phone. If the phone asks that "this type of file can harm your device", tap "OK" or "Download anyway" — this is a normal message for every app.'],
        ['film', 'Open the file', 'After it downloads, tap the APK file from the notification or in Files app → Downloads.'],
        ['settings', 'Allow this source', 'The first time, the phone says "not allowed to install from this source". Go to "Settings", turn ON "Allow from this source", then go back. (This happens only the first time.)'],
        ['shield', 'Install', 'If a warning appears from Google, tap "More details" → "Install anyway" — it appears because the app installs from outside the Play Store. Then tap "Install".'],
        ['check', 'Open the app', 'After installing, tap "Open" and log in with your account. Done!'],
    ];
@endphp
<div class="relative overflow-hidden">
    <div class="pointer-events-none absolute -top-48 left-1/2 size-[44rem] -translate-x-1/2 rounded-full bg-brand/25 blur-3xl"></div>

    <header class="relative mx-auto flex max-w-3xl items-center justify-between px-5 py-5 safe-top">
        <a href="{{ route('home') }}">@include('partials.logo')</a>
        <a href="{{ route('home') }}" class="btn-ghost !px-4 !py-2.5"><x-icon name="arrow-left" class="size-4"/> Back</a>
    </header>

    <main class="relative mx-auto max-w-3xl px-5 pb-20">
        {{-- App card --}}
        <div class="card overflow-hidden">
            <div class="grad flex items-center gap-4 p-6">
                <span class="grid size-16 shrink-0 place-items-center rounded-2xl bg-black/20 backdrop-blur">
                    <svg viewBox="0 0 24 24" class="size-8 text-white" fill="currentColor"><path d="M7 4.5v15a1 1 0 0 0 1.52.85l12-7.5a1 1 0 0 0 0-1.7l-12-7.5A1 1 0 0 0 7 4.5Z"/></svg>
                </span>
                <div>
                    <h1 class="text-xl font-extrabold">{{ setting('site_name') }}</h1>
                    <p class="text-sm text-white/85">Android App · Version {{ $version }}</p>
                </div>
            </div>
            <div class="grid grid-cols-3 divide-x divide-app-line border-b border-app-line text-center">
                <div class="p-3"><div class="text-sm font-bold">{{ $size }} MB</div><div class="text-[11px] text-app-muted">Size</div></div>
                <div class="p-3"><div class="text-sm font-bold">Android {{ $minAndroid }}+</div><div class="text-[11px] text-app-muted">Requires</div></div>
                <div class="p-3"><div class="text-sm font-bold">{{ $version }}</div><div class="text-[11px] text-app-muted">Version</div></div>
            </div>
            <div class="p-5">
                @if ($available)
                    <a href="{{ route('app.file') }}" class="btn-primary w-full !py-4 text-base">
                        <x-icon name="download" class="size-5" stroke="2.2"/> Download APK
                    </a>
                @else
                    <button disabled class="btn-ghost w-full !py-4 text-base">App coming soon</button>
                @endif
                <p class="mt-3 text-center text-xs text-app-muted">Open on an Android phone. Not available on iPhone yet — use the website.</p>
            </div>
        </div>

        {{-- Why it's safe / auto-update --}}
        <div class="mt-5 rounded-2xl border border-brand/30 bg-brand/10 p-4 text-sm text-white/90">
            <div class="flex items-start gap-3">
                <x-icon name="sparkles" class="mt-0.5 size-5 shrink-0 text-brand"/>
                <p>This app simply opens our website like an app on your phone, so anything new on the website appears in the app automatically — no need to download it again.</p>
            </div>
        </div>

        {{-- Steps --}}
        <h2 class="mt-8 text-lg font-bold">How to install?</h2>
        <div class="mt-4 space-y-3">
            @foreach ($steps as $i => [$icon, $title, $text])
                <div class="card flex gap-4 p-4">
                    <div class="relative shrink-0">
                        <span class="grad grid size-11 place-items-center rounded-xl"><x-icon :name="$icon" class="size-5"/></span>
                        <span class="absolute -right-1.5 -top-1.5 grid size-5 place-items-center rounded-full bg-app-bg text-[11px] font-bold ring-1 ring-app-line">{{ $i + 1 }}</span>
                    </div>
                    <div>
                        <h3 class="font-bold">{{ $title }}</h3>
                        <p class="mt-0.5 text-sm text-app-muted">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- FAQ --}}
        <h2 class="mt-8 text-lg font-bold">Aam sawal</h2>
        <div class="mt-4 space-y-2" x-data="{ open: 0 }">
            @foreach ([
                ['Why does a warning appear?', 'Because the app installs from outside the Play Store, Android shows a precautionary warning for every such app. You can continue with "Install anyway". The app is safe — it only opens our website.'],
                ['Download not working?', 'Open the link in Chrome or another browser. If the phone blocks it, tap "Download anyway" in the notification bar, or try again after a while.'],
                ['Install not working / "App not installed"', 'If an old version is already installed, uninstall it first, then install the new file. Also check your storage.'],
                ['How will the app update?', 'The app shows the website, so normal updates come automatically. You only need a new APK when the app itself (design or features) changes — we will let you know.'],
            ] as $i => [$q, $a])
                <div class="card overflow-hidden">
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" class="flex w-full items-center justify-between gap-3 p-4 text-left">
                        <span class="text-sm font-semibold">{{ $q }}</span>
                        <x-icon name="chevron-right" class="size-4 shrink-0 text-app-muted transition" x-bind:class="open === {{ $i }} ? 'rotate-90' : ''"/>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse class="px-4 pb-4 text-sm text-app-muted">{{ $a }}</div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('register') }}" class="btn-primary !px-7 !py-3.5">Create an account first</a>
        </div>
    </main>
</div>
{!! setting('body_scripts') !!}
</body>
</html>
