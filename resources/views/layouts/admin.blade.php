@php
    $pendingPayments = \App\Models\Payment::query()->where('status', 'pending')->count();
    $pendingWithdrawals = \App\Models\Withdrawal::query()->where('status', 'pending')->count();
    $nav = [
        ['admin.dashboard', 'Dashboard', 'grid', null],
        ['admin.videos.*', 'Videos', 'film', null, 'admin.videos.index'],
        ['admin.shorts.*', 'Auto Shorts', 'sparkles', null, 'admin.shorts.index'],
        ['admin.ads.*', 'Ad Units', 'megaphone', null, 'admin.ads.index'],
        ['admin.plans.*', 'Plans', 'crown', null, 'admin.plans.index'],
        ['admin.payments.*', 'Payments', 'card', $pendingPayments, 'admin.payments.index'],
        ['admin.gateway.*', 'Payment Gateway', 'wallet', null, 'admin.gateway.index'],
        ['admin.withdrawals.*', 'Withdrawals', 'banknotes', $pendingWithdrawals, 'admin.withdrawals.index'],
        ['admin.users.*', 'Users', 'users', null, 'admin.users.index'],
        ['admin.subadmins.*', 'Sub Admins', 'crown', null, 'admin.subadmins.index'],
        ['admin.fraud.*', 'Fraud Reports', 'shield', null, 'admin.fraud.index'],
        ['admin.revenue.*', 'Ad Revenue', 'chart', null, 'admin.revenue.index'],
        ['admin.notifications.*', 'Notifications', 'bolt', null, 'admin.notifications.index'],
        ['admin.settings.*', 'Settings', 'settings', null, 'admin.settings.edit'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    @include('partials.head')
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-800 antialiased" x-data="{ drawer: false }">

<aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-slate-900 px-3 py-5 text-slate-300 transition-transform lg:translate-x-0"
       :class="drawer && '!translate-x-0'">
    <div class="flex items-center justify-between px-2">
        <a href="{{ route('admin.dashboard') }}">@include('partials.logo')</a>
        <button class="lg:hidden" @click="drawer = false"><x-icon name="x" class="size-5"/></button>
    </div>
    <div class="mt-1 px-2 text-[11px] font-semibold uppercase tracking-widest text-slate-500">Admin panel</div>

    <nav class="no-scrollbar mt-6 flex flex-1 flex-col gap-0.5 overflow-y-auto">
        @foreach ($nav as $item)
            @php [$pattern, $label, $icon, $badge] = $item; $on = request()->routeIs($pattern); @endphp
            <a href="{{ route($item[4] ?? $pattern) }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $on ? 'bg-white/10 text-white' : 'hover:bg-white/5 hover:text-white' }}">
                <x-icon :name="$icon" class="size-5 {{ $on ? 'text-violet-400' : 'text-slate-500' }}"/>
                <span class="flex-1">{{ $label }}</span>
                @if ($badge)<span class="rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $badge }}</span>@endif
            </a>
        @endforeach
    </nav>

    <div class="space-y-1 border-t border-white/10 pt-3">
        <a href="{{ route('feed') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-white/5 hover:text-white">
            <x-icon name="play" class="size-5 text-slate-500"/> View app
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-white/5 hover:text-white">
                <x-icon name="logout" class="size-5 text-slate-500"/> Logout
            </button>
        </form>
    </div>
</aside>
<div x-show="drawer" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="drawer = false"></div>

<div class="lg:pl-64">
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:px-8">
        <button class="-ml-1 grid size-10 place-items-center rounded-lg hover:bg-slate-100 lg:hidden" @click="drawer = true"><x-icon name="menu" class="size-6"/></button>
        <h1 class="flex-1 truncate text-lg font-bold text-slate-900">@yield('title')</h1>
        @yield('actions')
    </header>

    <main class="p-4 lg:p-8">
        @if (session('success'))
            <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                <x-icon name="check" class="size-5" stroke="2.5"/> {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
