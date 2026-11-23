<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSubAdmin;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The device-id cookie is written by JavaScript, so it is not encrypted.
        $middleware->encryptCookies(except: ['pe_did']);
        // The payment gateway webhook is verified by HMAC signature, not CSRF.
        $middleware->validateCsrfTokens(except: ['pkpay/callback']);
        $middleware->web(append: [TrackUserActivity::class]);
        $middleware->alias(['admin' => EnsureAdmin::class, 'subadmin' => EnsureSubAdmin::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => match (true) {
            auth()->user()?->isAdmin() => route('admin.dashboard'),
            auth()->user()?->isSubAdmin() => route('team.dashboard'),
            default => route('feed'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
