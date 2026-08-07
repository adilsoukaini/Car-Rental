<?php

use Illuminate\Support\Facades\Route;
use Plugins\BookingEngine\Http\Controllers\BookingCheckoutController;

Route::middleware('web')->group(function () {
    Route::get('/vehicles/{vehicle}/book', [BookingCheckoutController::class, 'show'])->name('bookings.checkout');
    // Rate-limited so an attacker can't flood the pending-hold table with
    // cheap POSTs (each creates a real pending booking + Stripe PaymentIntent).
    Route::post('/vehicles/{vehicle}/book', [BookingCheckoutController::class, 'store'])
        ->name('bookings.store')
        ->middleware('throttle:10,1');
    // State-changing confirm is strictly POST + CSRF (it confirms the pending
    // booking and fires BookingConfirmed). The GET at this same URI is
    // deliberately non-mutating — it only renders a form that auto-POSTs back
    // here, so the Stripe 3D-Secure redirect (which is always a GET) can still
    // complete the flow without the confirmation itself ever happening on GET.
    Route::post('/bookings/{booking}/confirm', [BookingCheckoutController::class, 'confirm'])
        ->name('bookings.confirm');
    Route::get('/bookings/{booking}/confirm', [BookingCheckoutController::class, 'confirmReturn'])
        ->name('bookings.confirm.return');
});
