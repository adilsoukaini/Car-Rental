<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Models\HomepageContent;
use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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
        'locations' => Location::where('is_active', true)
            ->select('id', 'name', 'city')
            ->get(),
    ]);
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Dynamic XML sitemap — homepage, fleet listing, and every available
// vehicle's detail page. Only `available` vehicles are included (a
// `maintenance`-status vehicle already 404s on its detail page, so listing
// it here would be a soft-404). View: resources/views/sitemap.blade.php.
Route::get('/sitemap.xml', function () {
    $vehicles = Vehicle::where('status', 'available')->get();

    return response()->view('sitemap', ['vehicles' => $vehicles])
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

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

// Health check — reports status of all external dependencies. Used by
// monitoring tools, load balancers, and CI/CD smoke tests. Always returns
// 200 with a JSON body; individual dependency statuses are in the response.
Route::get('/health', function () {
    $checks = [
        'database' => 'ok',
        'meilisearch' => 'ok',
        'stripe' => 'ok',
        'storage' => 'ok',
    ];

    // Database
    try { DB::connection()->getPdo(); } catch (\Throwable $e) {
        $checks['database'] = 'error: ' . $e->getMessage();
    }

    // Meilisearch
    try {
        $meili = new \GuzzleHttp\Client(['timeout' => 2]);
        $resp = $meili->get('http://localhost:7700/health');
        if ($resp->getStatusCode() !== 200) $checks['meilisearch'] = 'unhealthy';
    } catch (\Throwable) {
        $checks['meilisearch'] = 'unreachable';
    }

    // Stripe — just check the key is configured
    $checks['stripe'] = (config('payments-stripe.secret_key') ?: env('STRIPE_SECRET')) ? 'configured' : 'missing';

    // Storage
    try { Storage::disk('public')->exists('.'); } catch (\Throwable $e) {
        $checks['storage'] = 'error: ' . $e->getMessage();
    }

    $allOk = !array_filter($checks, fn($v) => $v !== 'ok' && $v !== 'configured');
    return response()->json(['status' => $allOk ? 'healthy' : 'degraded', 'checks' => $checks]);
})->name('health');
