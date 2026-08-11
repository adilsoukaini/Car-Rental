<?php

use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as Respond;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
        $schedule->command('bookings:release-expired-holds')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command(\Illuminate\Queue\Console\WorkCommand::class, ['--stop-when-empty'])
            ->everyMinute()
            ->withoutOverlapping(5);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            CorrelationId::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Gateway webhooks are authenticated by their own signature/HMAC
        // verification (see each gateway's handleWebhook()), not a session —
        // a real browser session/CSRF token is never involved.
        //
        // /api/* is Bearer-token authenticated (Sanctum), never a browser
        // session, so it carries no CSRF token either. The `api` middleware
        // group doesn't include CSRF, but the exclusion is explicit here as
        // defense-in-depth: any future /api route accidentally registered
        // inside a `web` group must not be blocked for the mobile app.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Replace Laravel's bare error views with the themed Inertia error
        // pages (Errors/NotFound, Errors/ServerError) for storefront
        // requests. 503 is caught by the `>= 500` branch, covering
        // maintenance-mode. JSON/API requests are unaffected
        // (shouldRenderJsonWhen above runs first). Type-hinting the base
        // Symfony Response (not Illuminate\Http\Response) is required — the
        // finalize callback also receives RedirectResponse (validation
        // errors, auth redirects) and JsonResponse. The trailing `return
        // $response;` is load-bearing: this callback is the FINAL render
        // step, so returning null for any other status (403, 419, validation
        // 422, ...) would discard the real response entirely.
        $exceptions->respond(function (Respond $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 404) {
                return Inertia::render('Errors/NotFound', [])->toResponse($request)->setStatusCode(404);
            }

            if ($response->getStatusCode() >= 500) {
                return Inertia::render('Errors/ServerError', [])->toResponse($request)->setStatusCode(500);
            }

            return $response;
        });
    })->create();
