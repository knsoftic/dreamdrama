<!DOCTYPE html>
<html lang="en" class="bg-app-bg">
<head>
    @include('partials.head')
    {!! setting('head_scripts') !!}
</head>
<body class="min-h-dvh bg-app-bg font-sans text-white antialiased">
@php
    $steps = [
        ['download', 'App download karein', 'Neeche "Download APK" button dabayein. File (.apk) aap ke phone mein download ho jayegi. Agar phone "is type ki file nuqsan-deh ho sakti hai" poochhe to "OK" ya "Download anyway" dabayein — yeh har app ke liye normal message hai.'],
        ['film', 'File kholein', 'Download hone ke baad notification par ya Files app → Downloads mein ja kar APK file par tap karein.'],
        ['settings', 'Is source ko ijazat dein', 'Pehli dafa phone poochega: "Is source se install ki ijazat nahi". "Settings" par ja kar "Is source ko allow karein / Allow from this source" ON karein, phir back dabayein. (Yeh sirf pehli dafa hota hai.)'],
        ['shield', 'Install karein', 'Agar Google ki taraf se warning aaye, "More details" → "Install anyway" dabayein — yeh isliye aati hai kyunke app Play Store ke bahar se install ho rahi hai. Phir "Install" dabayein.'],
        ['check', 'App kholein', 'Install hone ke baad "Open" dabayein aur apne account se login karein. Bas!'],
    ];
@endphp
<div class="relative overflow-hidden">
    <div class="pointer-events-none absolute -top-48 left-1/2 size-[44rem] -translate-x-1/2 rounded-full bg-brand/25 blur-3xl"></div>

    <header class="relative mx-auto flex max-w-3xl items-center justify-between px-5 py-5 safe-top">
        <a href="{{ route('home') }}">@include('partials.logo')</a>
        <a href="{{ route('home') }}" class="btn-ghost !px-4 !py-2.5"><x-icon name="arrow-left" class="size-4"/> Wapas</a>
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
                <div class="p-3"><div class="text-sm font-bold">Android {{ $minAndroid }}+</div><div class="text-[11px] text-app-muted">Chahiye</div></div>
                <div class="p-3"><div class="text-sm font-bold">{{ $version }}</div><div class="text-[11px] text-app-muted">Version</div></div>
            </div>
            <div class="p-5">
                @if ($available)
                    <a href="{{ route('app.file') }}" class="btn-primary w-full !py-4 text-base">
                        <x-icon name="download" class="size-5" stroke="2.2"/> Download APK
                    </a>
                @else
                    <button disabled class="btn-ghost w-full !py-4 text-base">App jald available hogi</button>
                @endif
                <p class="mt-3 text-center text-xs text-app-muted">Android phone par kholein. iPhone par abhi app available nahi — website hi use karein.</p>
            </div>
        </div>

        {{-- Why it's safe / auto-update --}}
        <div class="mt-5 rounded-2xl border border-brand/30 bg-brand/10 p-4 text-sm text-white/90">
            <div class="flex items-start gap-3">
                <x-icon name="sparkles" class="mt-0.5 size-5 shrink-0 text-brand"/>
                <p>Yeh app hamari website ko hi phone mein app ki tarah kholti hai. Iska matlab website par jo bhi naya hota hai woh app mein khud aa jata hai — app dobara download karne ki zaroorat nahi.</p>
            </div>
        </div>

        {{-- Steps --}}
        <h2 class="mt-8 text-lg font-bold">Install kaise karein?</h2>
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
                ['Warning kyun aati hai?', 'Kyunke app Play Store ke bahar se install ho rahi hai, Android har aisi app par ehtiyaatan warning deta hai. Aap "Install anyway" se aage barh sakte hain. App mehfooz hai — yeh sirf hamari website kholti hai.'],
                ['Download nahi ho raha?', 'Chrome ya kisi aur browser mein link kholein. Agar phone block kare to notification bar mein "Download anyway" dabayein, ya kuch dair baad dobara koshish karein.'],
                ['Install nahi ho raha / "App not installed"', 'Purana version pehle se laga ho to pehle use uninstall karein, phir nayi file install karein. Storage bhi check karein.'],
                ['App update kaise hogi?', 'App website hi dikhati hai, is liye normal updates khud aa jati hain. Sirf tab nayi APK chahiye hogi jab app ka apna design/features badlein — hum bata denge.'],
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
            <a href="{{ route('register') }}" class="btn-primary !px-7 !py-3.5">Pehle account banayein</a>
        </div>
    </main>
</div>
{!! setting('body_scripts') !!}
</body>
</html>
