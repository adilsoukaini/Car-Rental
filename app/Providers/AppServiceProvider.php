<?php

namespace App\Providers;

use App\Core\Events\BookingConfirmed;
use App\Core\Listeners\SendBookingConfirmationEmail;
use App\Core\Support\PluginManager;
use App\Core\Support\SlotRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Must run in boot(), not register() — Eloquent's DB resolver isn't
        // available during register() (DatabaseServiceProvider::boot() sets it).
        // Plugin providers registered here get both register() and boot() called
        // immediately by Laravel because the app is already in boot phase.
        PluginManager::boot();

        Event::listen(BookingConfirmed::class, SendBookingConfirmationEmail::class);

        SlotRegistry::register('account.dashboardWidgets', 'Widgets/BookingHistory');

        Vite::prefetch(concurrency: 3);
    }
}
