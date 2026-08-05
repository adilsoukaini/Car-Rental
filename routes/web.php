<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ProfileController;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Replaces Laravel's default Welcome scaffold — reachability-audit finding
// #3. A single query for the featured set (rule 8) — no per-vehicle N+1.
Route::get('/', function () {
    return Inertia::render('Home', [
        'featuredVehicles' => Vehicle::where('status', 'available')
            ->latest()
            ->take(4)
            ->get(),
    ]);
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Throwaway Phase 3 verification page — proves the theme token system applies
// and swaps correctly. Remove once a real themed page exists to demonstrate this.
Route::get('/theme-test', function () {
    return Inertia::render('ThemeTest', [
        'activeTheme' => config('site.active_theme'),
    ]);
})->name('theme-test');

Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
