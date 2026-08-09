<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Plugins\BookingEngine\Http\Controllers\BookingCheckoutController;
use Plugins\DriverVerification\Http\Controllers\DriverVerificationController;

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

// Public auth — no token required.
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/register', [AuthController::class, 'register'])->name('api.register');

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
});

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
    ->name('api.bookings.confirm');
Route::post('/bookings/track', [BookingController::class, 'lookup'])
    ->name('api.bookings.track')
    ->middleware('throttle:20,1');
Route::get('/locations', [LocationController::class, 'index'])->name('api.locations.index');
