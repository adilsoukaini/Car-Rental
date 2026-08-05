<?php

use Illuminate\Support\Facades\Route;
use Plugins\BookingEngine\Http\Controllers\BookingCheckoutController;

Route::middleware('web')->group(function () {
    Route::get('/vehicles/{vehicle}/book', [BookingCheckoutController::class, 'show'])->name('bookings.checkout');
    Route::post('/vehicles/{vehicle}/book', [BookingCheckoutController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}/confirm', [BookingCheckoutController::class, 'confirm'])->name('bookings.confirm');
});
