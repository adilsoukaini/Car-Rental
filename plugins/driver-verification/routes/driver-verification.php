<?php

use Illuminate\Support\Facades\Route;
use Plugins\DriverVerification\Http\Controllers\DriverVerificationController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/account/driver-verification', [DriverVerificationController::class, 'show'])
        ->name('driver-verification.show');
    Route::post('/account/driver-verification', [DriverVerificationController::class, 'store'])
        ->name('driver-verification.store');
});
