<?php

use Illuminate\Support\Facades\Route;
use Plugins\FleetManagement\Http\Controllers\VehicleController;

Route::middleware('web')->group(function () {
    Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
});
