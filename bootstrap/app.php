<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // This project's first scheduled task (2026-08-04) — releases
        // pending bookings whose payment-collection hold has expired.
        // Referenced by its command signature string, not the plugin's
        // command class, so core never imports the plugin (Hard Rule 1).
        // A no-op if booking-engine is disabled or the command doesn't
        // exist — Artisan silently skips an unresolvable scheduled command.
        $schedule->command('bookings:release-expired-holds')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Gateway webhooks are authenticated by their own signature/HMAC
        // verification (see each gateway's handleWebhook()), not a session —
        // a real browser session/CSRF token is never involved.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
