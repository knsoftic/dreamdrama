<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AppApiController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserPagesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [UserPagesController::class, 'home'])->name('home');

// Lets phones "install" the site like an app (Add to Home screen).
Route::get('/manifest.webmanifest', fn () => response()->json([
    'name' => setting('site_name'),
    'short_name' => setting('site_name'),
    'description' => setting('site_tagline'),
    'start_url' => '/feed',
    'scope' => '/',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#08080d',
    'theme_color' => '#08080d',
    'icons' => [
        ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
])->header('Content-Type', 'application/manifest+json'))->name('manifest');

// Invite link: /r/ABC1234 → register page with the invite code filled in
Route::get('/r/{code}', [UserPagesController::class, 'referralLink'])->name('invite.link');

// Android app: install instructions page, and the APK streamed from our own domain
Route::get('/download', [UserPagesController::class, 'download'])->name('download');
Route::get('/download/apk', [UserPagesController::class, 'appFile'])->name('app.file')->middleware('throttle:30,1');

// google-services.json for the Android build (public; it is embedded in the APK anyway)
Route::get('/google-services.json', [UserPagesController::class, 'googleServices'])->name('google-services');

// Payment gateway webhook (public, signature-verified, CSRF-exempt)
Route::post('/pkpay/callback', \App\Http\Controllers\GatewayCallbackController::class)
    ->name('pkpay.callback')->middleware('throttle:120,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // App pages
    Route::get('/feed', [UserPagesController::class, 'feed'])->name('feed');
    Route::get('/earnings', [UserPagesController::class, 'earnings'])->name('earnings');
    Route::get('/plans', [UserPagesController::class, 'plans'])->name('plans');
    Route::get('/plans/{plan:slug}/checkout', [UserPagesController::class, 'checkout'])->name('plans.checkout');
    Route::post('/plans/{plan:slug}/checkout', [UserPagesController::class, 'pay'])->name('plans.pay')->middleware('throttle:10,1');
    Route::post('/plans/{plan:slug}/pay-gateway', [UserPagesController::class, 'payGateway'])->name('plans.pay-gateway')->middleware('throttle:10,1');
    Route::get('/payments/{payment}/return', [UserPagesController::class, 'paymentReturn'])->name('payments.return');
    Route::get('/wallet', [UserPagesController::class, 'wallet'])->name('wallet');
    Route::post('/wallet/withdraw', [UserPagesController::class, 'withdraw'])->name('wallet.withdraw')->middleware('throttle:10,1');
    Route::get('/invite', [UserPagesController::class, 'invite'])->name('invite');
    Route::get('/profile', [UserPagesController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserPagesController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [UserPagesController::class, 'updatePassword'])->name('profile.password');

    // Feed JSON endpoints
    Route::prefix('app')->name('app.')->middleware('throttle:240,1')->group(function () {
        Route::get('/feed', [AppApiController::class, 'feed'])->name('feed');
        Route::get('/state', [AppApiController::class, 'state'])->name('state');
        Route::post('/watch/start', [AppApiController::class, 'watchStart'])->name('watch.start');
        Route::post('/watch/heartbeat', [AppApiController::class, 'heartbeat'])->name('watch.heartbeat');
        Route::post('/watch/end', [AppApiController::class, 'watchEnd'])->name('watch.end');
        Route::post('/ad/start', [AppApiController::class, 'adStart'])->name('ad.start');
        Route::post('/ad/complete', [AppApiController::class, 'adComplete'])->name('ad.complete');
        Route::post('/push-token', [AppApiController::class, 'pushToken'])->name('push-token');
    });
    Route::get('/ad/frame/{adUnit}', [AppApiController::class, 'adFrame'])->name('ad.frame');

    // Sub-admin panel (team)
    Route::prefix('team')->name('team.')->middleware('subadmin')->group(function () {
        Route::get('/', \App\Http\Controllers\SubAdmin\DashboardController::class)->name('dashboard');
        Route::get('/users', [\App\Http\Controllers\SubAdmin\UserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/block', [\App\Http\Controllers\SubAdmin\UserController::class, 'toggleBlock'])->name('users.block');
        Route::get('/deposits', [\App\Http\Controllers\SubAdmin\DepositController::class, 'index'])->name('deposits.index');
        Route::get('/withdrawals', [\App\Http\Controllers\SubAdmin\WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('/withdrawals', [\App\Http\Controllers\SubAdmin\WithdrawalController::class, 'store'])->name('withdrawals.store')->middleware('throttle:10,1');
    });

    // Admin panel
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('videos', Admin\VideoController::class)->except('show');
        Route::post('videos/{video}/toggle', [Admin\VideoController::class, 'toggle'])->name('videos.toggle');
        Route::resource('ads', Admin\AdUnitController::class)->except('show');
        Route::resource('plans', Admin\PlanController::class)->except('show');

        Route::get('payments', [Admin\FinanceController::class, 'payments'])->name('payments.index');
        Route::post('payments/{payment}/approve', [Admin\FinanceController::class, 'approvePayment'])->name('payments.approve');
        Route::post('payments/{payment}/reject', [Admin\FinanceController::class, 'rejectPayment'])->name('payments.reject');
        Route::post('payments/{payment}/check', [Admin\FinanceController::class, 'checkPayment'])->name('payments.check');

        Route::get('gateway', [Admin\PaymentGatewayController::class, 'index'])->name('gateway.index');
        Route::post('gateway', [Admin\PaymentGatewayController::class, 'save'])->name('gateway.save');
        Route::post('gateway/test', [Admin\PaymentGatewayController::class, 'test'])->name('gateway.test');
        Route::post('gateway/clear', [Admin\PaymentGatewayController::class, 'clearSecret'])->name('gateway.clear');

        Route::get('withdrawals', [Admin\FinanceController::class, 'withdrawals'])->name('withdrawals.index');
        Route::post('withdrawals/{withdrawal}/pay', [Admin\FinanceController::class, 'payWithdrawal'])->name('withdrawals.pay');
        Route::post('withdrawals/{withdrawal}/reject', [Admin\FinanceController::class, 'rejectWithdrawal'])->name('withdrawals.reject');

        Route::get('revenue', [Admin\FinanceController::class, 'revenue'])->name('revenue.index');
        Route::post('revenue', [Admin\FinanceController::class, 'storeRevenue'])->name('revenue.store');
        Route::delete('revenue/{entry}', [Admin\FinanceController::class, 'destroyRevenue'])->name('revenue.destroy');

        Route::get('sub-admins', [Admin\SubAdminController::class, 'index'])->name('subadmins.index');
        Route::get('sub-admins/create', [Admin\SubAdminController::class, 'create'])->name('subadmins.create');
        Route::post('sub-admins', [Admin\SubAdminController::class, 'store'])->name('subadmins.store');
        Route::post('sub-admins/promote', [Admin\SubAdminController::class, 'promote'])->name('subadmins.promote');
        Route::post('sub-admins/{user}/code', [Admin\SubAdminController::class, 'updateCode'])->name('subadmins.code');
        Route::post('sub-admins/{user}/commission', [Admin\SubAdminController::class, 'updateCommission'])->name('subadmins.commission');
        Route::post('sub-admins/{user}/block', [Admin\SubAdminController::class, 'toggleBlock'])->name('subadmins.block');
        Route::post('sub-admins/{user}/demote', [Admin\SubAdminController::class, 'demote'])->name('subadmins.demote');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::post('users/{user}/block', [Admin\UserController::class, 'toggleBlock'])->name('users.block');
        Route::post('users/{user}/flag', [Admin\UserController::class, 'toggleFlag'])->name('users.flag');
        Route::post('users/{user}/balance', [Admin\UserController::class, 'adjustBalance'])->name('users.balance');
        Route::post('users/{user}/plan', [Admin\UserController::class, 'setPlan'])->name('users.plan');
        Route::get('fraud', [Admin\UserController::class, 'fraud'])->name('fraud.index');

        Route::get('notifications', [Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/config', [Admin\NotificationController::class, 'saveConfig'])->name('notifications.config');
        Route::post('notifications/google-services', [Admin\NotificationController::class, 'saveGoogleServices'])->name('notifications.google-services');
        Route::post('notifications/send', [Admin\NotificationController::class, 'send'])->name('notifications.send');

        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
    });
});
