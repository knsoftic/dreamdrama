@php
    $user = auth()->user();
    $nav = [
        ['route' => 'feed', 'label' => 'Home', 'icon' => 'home'],
        ['route' => 'earnings', 'label' => 'Earnings', 'icon' => 'chart'],
        ['route' => 'plans', 'label' => 'Plans', 'icon' => 'crown', 'center' => true],
        ['route' => 'wallet', 'label' => 'Wallet', 'icon' => 'wallet'],
        ['route' => 'profile', 'label' => 'Profile', 'icon' => 'user'],
    ];
    $fullscreen = $__env->hasSection('fullscreen');
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full bg-app-bg">
<head>
    @include('partials.head')
    {!! setting('head_scripts') !!}
</head>
<body class="min-h-full bg-app-bg font-sans text-white antialiased selection:bg-brand/40">

{{-- ===== Desktop sidebar ===== --}}
<aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-app-line bg-app-card/60 px-4 py-6 backdrop-blur lg:flex">
    <a href="{{ route('feed') }}" class="px-2">@include('partials.logo')</a>

    <nav class="mt-8 flex flex-col gap-1">
        @foreach ($nav as $item)
            @php $on = request()->routeIs($item['route'].'*'); @endphp
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ $on ? 'bg-white/10 text-white' : 'text-app-muted hover:bg-white/5 hover:text-white' }}">
                <x-icon :name="$item['icon']" class="size-5 {{ $on ? 'text-brand' : '' }}"/>
                {{ $item['label'] }}
            </a>
        @endforeach
        @if ($user->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="mt-2 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-amber-300 hover:bg-white/5">
                <x-icon name="settings" class="size-5"/> Admin Panel
            </a>
        @endif
    </nav>

    <div class="mt-auto space-y-3">
        <a href="{{ route('wallet') }}" class="block rounded-2xl grad p-4 shadow-lg shadow-brand/20">
            <div class="text-xs font-medium text-white/80">Wallet balance</div>
            <div class="mt-1 text-2xl font-extrabold" data-balance>{{ money($user->balance) }}</div>
            <div class="mt-2 inline-flex items-center gap-1 rounded-full bg-black/20 px-2 py-0.5 text-[11px] font-semibold">
                <x-icon name="crown" class="size-3.5"/> {{ $user->currentPlan()->name }} plan
            </div>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-app-muted hover:bg-white/5 hover:text-white">
                <x-icon name="logout" class="size-5"/> Logout
            </button>
        </form>
    </div>
</aside>

<div class="lg:pl-64">
    @unless ($fullscreen)
        {{-- ===== Mobile app bar ===== --}}
        <header class="sticky top-0 z-30 border-b border-app-line bg-app-bg/85 backdrop-blur-xl safe-top lg:hidden">
            <div class="relative flex h-14 items-center justify-between px-4">
                @hasSection('back')
                    <a href="@yield('back')" class="-ml-2 grid size-10 place-items-center rounded-full active:bg-white/10"><x-icon name="arrow-left" class="size-5"/></a>
                @else
                    <a href="{{ route('feed') }}">@include('partials.logo')</a>
                @endif
                <span class="absolute left-1/2 -translate-x-1/2 text-base font-bold">@hasSection('back')@yield('title')@endif</span>
                <a href="{{ route('wallet') }}" class="chip bg-coin/15 text-coin">
                    <x-icon name="coin" class="size-4"/> <span data-balance>{{ money($user->balance) }}</span>
                </a>
            </div>
        </header>

        {{-- ===== Desktop page header ===== --}}
        <header class="hidden items-center justify-between px-8 pt-8 lg:flex">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">@yield('title')</h1>
                @hasSection('subtitle')<p class="mt-1 text-sm text-app-muted">@yield('subtitle')</p>@endif
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('feed') }}" class="btn-primary !py-2.5"><x-icon name="play" class="size-4" solid/> Watch & Earn</a>
                <a href="{{ route('profile') }}" class="grid size-10 place-items-center rounded-full bg-app-card2 text-sm font-bold ring-1 ring-app-line">{{ $user->initials() }}</a>
            </div>
        </header>
    @endunless

    <main class="{{ $fullscreen ? '' : 'mx-auto max-w-5xl px-4 pt-4 pb-tabbar lg:px-8 lg:pt-6' }}">
        @yield('content')
    </main>
</div>

{{-- ===== Mobile bottom tab bar ===== --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-app-line bg-app-bg/95 backdrop-blur-xl safe-bottom lg:hidden">
    <div class="mx-auto grid h-16 max-w-md grid-cols-5">
        @foreach ($nav as $item)
            @php $on = request()->routeIs($item['route'].'*'); @endphp
            @if (! empty($item['center']))
                <a href="{{ route($item['route']) }}" class="flex flex-col items-center justify-center">
                    <span class="grad -mt-6 grid size-14 place-items-center rounded-2xl shadow-lg shadow-brand/40 ring-4 ring-app-bg">
                        <x-icon :name="$item['icon']" class="size-6 text-white" stroke="2"/>
                    </span>
                    <span class="mt-0.5 text-[10px] font-semibold {{ $on ? 'text-white' : 'text-app-muted' }}">{{ $item['label'] }}</span>
                </a>
            @else
                <a href="{{ route($item['route']) }}" class="flex flex-col items-center justify-center gap-1 {{ $on ? 'text-white' : 'text-app-muted' }}">
                    <x-icon :name="$item['icon']" class="size-6" :stroke="$on ? 2.2 : 1.7"/>
                    <span class="text-[10px] font-semibold">{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>
</nav>

@include('partials.flash')
@stack('scripts')
{!! setting('body_scripts') !!}
</body>
</html>
