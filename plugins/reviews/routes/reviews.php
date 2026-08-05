<?php

use Illuminate\Support\Facades\Route;
use Plugins\Reviews\Http\Controllers\ReviewController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/vehicles/{vehicle}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});
