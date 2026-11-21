<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The device-id cookie is written by JavaScript, so it is not encrypted.
        $middleware->encryptCookies(except: ['pe_did']);
        $middleware->web(append: [TrackUserActivity::class]);
        $middleware->alias(['admin' => EnsureAdmin::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('feed'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
