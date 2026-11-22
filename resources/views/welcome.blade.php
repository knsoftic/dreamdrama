<!DOCTYPE html>
<html lang="en" class="bg-app-bg">
<head>
    @include('partials.head')
    {!! setting('head_scripts') !!}
</head>
<body class="min-h-dvh bg-app-bg font-sans text-white antialiased">
@php
    $appOn = setting('app_download_enabled', '1') === '1';
    $footer = setting('footer_text') ?: ('© '.date('Y').' '.setting('site_name'));
@endphp
<div class="relative overflow-hidden">
    <div class="pointer-events-none absolute -top-48 left-1/2 size-[44rem] -translate-x-1/2 rounded-full bg-brand/25 blur-3xl"></div>
    <div class="pointer-events-none absolute top-80 -right-40 size-[30rem] rounded-full bg-brand-2/15 blur-3xl"></div>

    <header class="relative mx-auto flex max-w-6xl items-center justify-between px-5 py-5 safe-top">
        @include('partials.logo')
        <div class="flex items-center gap-2">
            @if ($appOn)
                <a href="{{ route('download') }}" class="btn-ghost !px-4 !py-2.5"><x-icon name="download" class="size-4"/> <span class="hidden sm:inline">App</span></a>
            @endif
            <a href="{{ route('login') }}" class="btn-ghost !px-4 !py-2.5">Login</a>
            <a href="{{ route('register') }}" class="btn-primary hidden !px-4 !py-2.5 sm:inline-flex">Register</a>
        </div>
    </header>

    <section class="relative mx-auto grid max-w-6xl items-center gap-12 px-5 pt-8 pb-16 lg:grid-cols-2 lg:pt-16">
        <div>
            <span class="chip bg-white/10 text-white/90"><x-icon name="sparkles" class="size-4 text-coin"/> {{ setting('hero_badge') }}</span>
            <h1 class="mt-5 text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                {{ setting('hero_title') }}<br><span class="grad-text">{{ setting('hero_highlight') }}</span>
            </h1>
            <p class="mt-5 max-w-lg text-lg text-app-muted">{{ setting('hero_subtitle') }}</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('register') }}" class="btn-primary !px-7 !py-4 text-base">{{ setting('hero_primary_btn') }}</a>
                @if ($appOn)
                    <a href="{{ route('download') }}" class="btn-ghost !px-7 !py-4 text-base"><x-icon name="download" class="size-5"/> Android app</a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost !px-7 !py-4 text-base">{{ setting('hero_secondary_btn') }}</a>
                @endif
            </div>
            <div class="mt-10 grid max-w-md grid-cols-3 gap-3">
                @foreach ([['film', 'Videos', 'per second'], ['megaphone', 'Ads', 'fixed reward'], ['wallet', 'Withdraw', 'mobile wallet']] as [$icon, $a, $b])
                    <div class="card p-3 text-center">
                        <x-icon :name="$icon" class="mx-auto size-6 text-brand"/>
                        <div class="mt-2 text-sm font-bold">{{ $a }}</div>
                        <div class="text-[11px] text-app-muted">{{ $b }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Phone mockup --}}
        <div class="relative mx-auto hidden w-72 lg:block">
            <div class="aspect-[9/19] rounded-[2.75rem] border-[10px] border-[#1f1f2b] bg-black p-1 shadow-2xl shadow-brand/20">
                <div class="relative h-full overflow-hidden rounded-[2rem] grad">
                    <div class="absolute inset-x-3 top-4 flex justify-between text-[10px] font-semibold">
                        <span class="rounded-full bg-black/40 px-2 py-1">🎬 4/10 · 📢 3/10</span>
                        <span class="rounded-full bg-black/40 px-2 py-1 text-coin">{{ money(126.40) }}</span>
                    </div>
                    <div class="absolute inset-0 grid place-items-center"><span class="grid size-16 place-items-center rounded-full bg-black/30 backdrop-blur"><x-icon name="play" class="ml-1 size-8" solid/></span></div>
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-4 pt-16">
                        <div class="text-sm font-bold">Funny moments compilation 😂</div>
                        <div class="mt-1 text-[11px] text-white/80">Earn for every second watched</div>
                    </div>
                    <div class="absolute right-3 top-16 animate-pulse rounded-full bg-emerald-500 px-2 py-1 text-[10px] font-bold">+{{ money(0.04, 2) }}</div>
                </div>
            </div>
        </div>
    </section>

    @if (setting('show_how', '1') === '1')
        <section class="relative mx-auto max-w-6xl px-5 pb-16">
            <h2 class="text-center text-2xl font-extrabold sm:text-3xl">{{ setting('how_title') }}</h2>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['user', 'Create account', 'Register free with your mobile number.'],
                    ['play', 'Watch videos', 'Every second of earning adds up in the cycle.'],
                    ['megaphone', 'Ad break', 'Watch ads in each cycle — a fixed reward per ad.'],
                    ['banknotes', 'Withdraw', 'Money to your wallet when the cycle completes, then withdraw.'],
                ] as $i => [$icon, $title, $text])
                    <div class="card p-5">
                        <div class="flex items-center gap-3">
                            <span class="grad grid size-10 place-items-center rounded-xl"><x-icon :name="$icon" class="size-5"/></span>
                            <span class="text-xs font-bold text-app-muted">STEP {{ $i + 1 }}</span>
                        </div>
                        <h3 class="mt-4 font-bold">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-app-muted">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($appOn)
        <section class="relative mx-auto max-w-6xl px-5 pb-16">
            <div class="grad relative overflow-hidden rounded-3xl p-8 shadow-xl shadow-brand/20 sm:p-10">
                <div class="absolute -right-10 -top-10 size-48 rounded-full bg-white/10"></div>
                <div class="relative flex flex-col items-center gap-6 text-center sm:flex-row sm:text-left">
                    <span class="grid size-20 shrink-0 place-items-center rounded-3xl bg-black/20 backdrop-blur">
                        <svg viewBox="0 0 24 24" class="size-10 text-white" fill="currentColor"><path d="M7 4.5v15a1 1 0 0 0 1.52.85l12-7.5a1 1 0 0 0 0-1.7l-12-7.5A1 1 0 0 0 7 4.5Z"/></svg>
                    </span>
                    <div class="flex-1">
                        <h2 class="text-2xl font-extrabold">{{ setting('site_name') }} Android App</h2>
                        <p class="mt-1 text-white/85">Mobile par behtar tajurba — phone par app ki tarah chalega. Version {{ setting('app_version') }} · {{ setting('app_size_mb') }} MB</p>
                    </div>
                    <a href="{{ route('download') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-4 text-base font-bold text-black active:scale-[.98]">
                        <x-icon name="download" class="size-5" stroke="2.2"/> Download the app
                    </a>
                </div>
            </div>
        </section>
    @endif

    @if (setting('show_plans', '1') === '1' && $plans->isNotEmpty())
        <section class="relative mx-auto max-w-6xl px-5 pb-20">
            <h2 class="text-center text-2xl font-extrabold sm:text-3xl">Plans</h2>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="relative overflow-hidden rounded-3xl border border-app-line bg-app-card p-6">
                        <div class="absolute inset-x-0 top-0 h-1" style="background: {{ $plan->color }}"></div>
                        <h3 class="text-lg font-extrabold" style="color: {{ $plan->color }}">{{ $plan->name }}</h3>
                        <div class="mt-2 text-3xl font-extrabold">{{ $plan->isFree() ? 'Free' : money($plan->price, 0) }}</div>
                        <div class="mt-4 text-white/85">@include('plans._features', ['plan' => $plan])</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <footer class="relative border-t border-app-line py-8 text-center text-sm text-app-muted">
        {{ $footer }}
        @if ($appOn) · <a href="{{ route('download') }}" class="hover:text-white">Android app</a>@endif
    </footer>
</div>
{!! setting('body_scripts') !!}
</body>
</html>
