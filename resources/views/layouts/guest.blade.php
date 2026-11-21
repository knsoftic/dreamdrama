<!DOCTYPE html>
<html lang="en" class="h-full bg-app-bg">
<head>
    @include('partials.head')
    {!! setting('head_scripts') !!}
</head>
<body class="min-h-full bg-app-bg font-sans text-white antialiased">
<div class="relative flex min-h-dvh flex-col overflow-hidden lg:items-center lg:justify-center lg:py-12">
    <div class="pointer-events-none absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-brand/25 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-48 -right-24 size-[28rem] rounded-full bg-brand-2/20 blur-3xl"></div>

    <div class="relative flex w-full flex-1 flex-col px-6 pt-10 pb-8 safe-top lg:max-w-md lg:flex-none lg:rounded-3xl lg:border lg:border-app-line lg:bg-app-card/80 lg:p-10 lg:shadow-2xl lg:backdrop-blur">
        <a href="{{ route('home') }}" class="mb-10 self-start">@include('partials.logo')</a>
        @yield('content')
    </div>
</div>
@include('partials.flash', ['hideErrors' => true])
</body>
</html>
