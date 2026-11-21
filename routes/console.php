<?php

use App\Models\Subscription;
use Illuminate\Support\Facades\Schedule;

// Mark finished subscriptions as expired (users fall back to the default plan automatically).
Schedule::call(function () {
    Subscription::query()
        ->where('status', 'active')
        ->whereNotNull('ends_at')
        ->where('ends_at', '<=', now())
        ->update(['status' => 'expired']);
})->name('expire-subscriptions')->hourly();
