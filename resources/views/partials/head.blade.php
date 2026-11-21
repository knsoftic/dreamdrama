<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#08080d">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ setting('site_name') }}">
<title>@hasSection('title')@yield('title') · @endif{{ setting('site_name') }}</title>
<link rel="manifest" href="{{ route('manifest') }}">
<link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>window.APP = { currency: @json(setting('currency_symbol', 'Rs')) };</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Admin-chosen theme colors override the defaults for the whole app --}}
<style>
    :root {
        --color-brand: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) setting('brand_color', '#8b5cf6')) ? setting('brand_color') : '#8b5cf6' }};
        --color-brand-2: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) setting('brand_color_2', '#ec4899')) ? setting('brand_color_2') : '#ec4899' }};
    }
</style>
