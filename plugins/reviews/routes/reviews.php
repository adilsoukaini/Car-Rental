<?php

use Illuminate\Support\Facades\Route;
use Plugins\Reviews\Http\Controllers\ReviewController;

Route::middleware(['web', 'auth'])->group(function () {
    // Rate-limited so a single user can't spam the review form.
    Route::post('/vehicles/{vehicle}/reviews', [ReviewController::class, 'store'])
        ->name('reviews.store')
        ->middleware('throttle:20,1');
});
