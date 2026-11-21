<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('money')) {
    function money(float|string|null $amount, int $decimals = 2): string
    {
        return setting('currency_symbol', 'Rs').' '.number_format((float) $amount, $decimals);
    }
}

if (! function_exists('csv_setting')) {
    /** Turn a comma separated setting into a clean array. */
    function csv_setting(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) setting($key, '')))));
    }
}

if (! function_exists('is_app_request')) {
    /** True when the request comes from our Android app (it tags its User-Agent). */
    function is_app_request(): bool
    {
        return str_contains((string) request()->userAgent(), 'DramaEarnApp');
    }
}
