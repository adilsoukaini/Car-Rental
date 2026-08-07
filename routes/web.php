<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Models\HomepageContent;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Replaces Laravel's default Welcome scaffold — reachability-audit finding
// #3. A single query for the featured set (rule 8) — no per-vehicle N+1.
// The singleton homepage_content row (admin-editable via the Filament
// Homepage Content page) is passed along so the hero/value-prop/CTA copy is
// operator-editable without a deploy. Passed only here, not shared globally,
// since no other page consumes it.
Route::get('/', function () {
    return Inertia::render('Home', [
        'featuredVehicles' => Vehicle::where('status', 'available')
            ->latest()
            ->take(4)
            ->get(),
        'homepageContent' => HomepageContent::current()->only([
            'hero_title',
            'hero_subtitle',
            'hero_cta_text',
            'hero_cta_link',
            'features_title',
            'features_subtitle',
            'cta_band_title',
            'cta_band_subtitle',
        ]),
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

// Search autocomplete — rate-limited so the debounced suggestions fetch can't
// be hammered. Returns a JSON array of at most 5 matching available vehicles.
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])
    ->name('search.suggestions')
    ->middleware('throttle:30,1');

// Public booking lookup — registered before bookings/{booking} so "track" is
// never captured by the route-model-bound show route.
Route::get('/bookings/track', [BookingController::class, 'track'])->name('bookings.track');
Route::post('/bookings/track', [BookingController::class, 'lookup'])->name('bookings.track.lookup')->middleware('throttle:20,1');

Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
Route::get('/admin/vehicle-import-template', [App\Filament\Pages\BulkVehicleImport::class, 'downloadTemplate'])
    ->middleware(['web', 'auth'])->name('filament.admin.vehicle-import-template');
