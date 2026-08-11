<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ConditionReportController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PushNotificationController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Plugins\BookingEngine\Http\Controllers\BookingCheckoutController;
use Plugins\DriverVerification\Http\Controllers\DriverVerificationController;
use Plugins\Reviews\Http\Controllers\ReviewController;

/**
 * Token-based JSON API for the mobile app (documented in
 * ../car-rental-mobile/docs/event-registry.md). Loaded by bootstrap/app.php
 * via withRouting(api: ...) → every route here gets the /api prefix and the
 * `api` middleware group (no CSRF — auth is Bearer tokens via Sanctum).
 *
 * Controllers deliberately reuse the web side's business logic:
 *   - fleet listing/detail  → Api\VehicleController → VehicleCatalogService
 *     (the same service the storefront fleet controller uses)
 *   - book/confirm          → BookingCheckoutController (same plugin
 *     controller, API-aware: returns JSON for /api/* requests)
 *   - driver verification   → DriverVerificationController (same plugin
 *     controller, API-aware)
 *   - search suggestions    → SearchController::suggestions (already JSON)
 *   - booking lookup/cancel → Api\BookingController → BookingLookupService /
 *     BookingCancellationService (shared with the web side)
 *
 * Rate limits mirror the web routes (security hardening phase) — the booking
 * hold endpoint is the cheapest DoS vector, so it gets the tightest limit.
 */

// Public auth — no token required. Throttled to prevent brute-force.
Route::post('/login', [AuthController::class, 'login'])->name('api.login')
    ->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register'])->name('api.register')
    ->middleware('throttle:5,1');

// Token-authenticated endpoints.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'user'])->name('api.user');

    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('api.bookings.show');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('api.bookings.cancel');
    Route::get('/my-bookings', [BookingController::class, 'myBookings'])->name('api.my-bookings');

    Route::get('/account/driver-verification', [DriverVerificationController::class, 'show'])
        ->name('api.driver-verification.show');
    Route::post('/account/driver-verification', [DriverVerificationController::class, 'store'])
        ->name('api.driver-verification.store')
        ->middleware('throttle:20,1');

    // Authenticated review submission — one review per user per vehicle. Mirrors
    // the web route's throttle (security hardening phase).
    Route::post('/vehicles/{vehicle}/reviews', [ReviewController::class, 'store'])
        ->name('api.vehicles.reviews.store')
        ->middleware('throttle:20,1');

    // Photo check-in/check-out ("état des lieux") — the owner files a condition
    // report against their own booking. Multipart: stage + description + up to
    // 6 photos. Throttled like the other evidence-submission endpoints.
    Route::post('/bookings/{booking}/condition-report', [ConditionReportController::class, 'store'])
        ->name('api.bookings.condition-report')
        ->middleware('throttle:20,1');

    // Notification inbox — list + mark read. Capped at 50 most recent.
    // These work for both the mobile app (Bearer token) and the web storefront
    // (session cookie) since auth:sanctum accepts either.
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('api.notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('api.notifications.unread-count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('api.notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('api.notifications.read-all');

});

// Push registration — used by BOTH the mobile app (Bearer token) and the web
// storefront (browser session cookie). The `api` middleware group alone doesn't
// start a session, so the `web` group is added here: a same-origin browser
// fetch to /api/push/register authenticates with its session cookie, while the
// mobile app keeps authenticating with a Bearer token (auth:sanctum accepts
// either). CSRF stays excluded for /api/* (bootstrap/app.php).
Route::middleware(['web', 'auth:sanctum'])->group(function () {
    // Register a device/subscription on login/app-start/after permission grant,
    // unregister on logout or when the user disables notifications. /test sends
    // a push to the authenticated user's own devices to verify the chain.
    Route::post('/push/register', [PushNotificationController::class, 'register'])
        ->name('api.push.register')
        ->middleware('throttle:20,1');
    Route::post('/push/unregister', [PushNotificationController::class, 'unregister'])
        ->name('api.push.unregister')
        ->middleware('throttle:20,1');
    Route::get('/push/test', [PushNotificationController::class, 'test'])
        ->name('api.push.test')
        ->middleware('throttle:10,1');
});

// The VAPID public key the browser needs to create a push subscription.
// PUBLIC by design — the key is meant to be shared with every client (the
// PRIVATE key never leaves the server). No auth, so the storefront can fetch it
// before subscribing. Returns 503 when VAPID isn't configured, which the
// frontend treats as "push unavailable — degrade silently".
Route::get('/push/vapid-public-key', [PushNotificationController::class, 'vapidPublicKey'])
    ->name('api.push.vapid-public-key');

// Public catalog + booking-creation endpoints. book/confirm honor a Bearer
// token when present (an authenticated customer is attached to the booking)
// but must also work for guests, so they are intentionally NOT behind
// auth:sanctum.
Route::get('/vehicles', [VehicleController::class, 'index'])->name('api.vehicles.index');
Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->name('api.vehicles.show');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])
    ->name('api.search.suggestions')
    ->middleware('throttle:30,1');
Route::post('/vehicles/{vehicle}/book', [BookingCheckoutController::class, 'store'])
    ->name('api.bookings.store')
    ->middleware('throttle:10,1');
Route::post('/bookings/{booking}/confirm', [BookingCheckoutController::class, 'confirm'])
    ->name('api.bookings.confirm')
    ->middleware('throttle:10,1');
Route::post('/bookings/track', [BookingController::class, 'lookup'])
    ->name('api.bookings.track')
    ->middleware('throttle:20,1');
Route::get('/locations', [LocationController::class, 'index'])->name('api.locations.index');
