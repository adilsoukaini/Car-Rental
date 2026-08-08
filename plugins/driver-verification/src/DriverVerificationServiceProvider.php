<?php

declare(strict_types=1);

namespace Plugins\DriverVerification;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\DriverVerification\Filament\Resources\DriverVerifications\DriverVerificationResource;

class DriverVerificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/driver-verification.php', 'driver-verification');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/driver-verification.php');

        // The `booking.driverEligibilityCheck` filter is intentionally NOT
        // registered anymore — online license verification is optional
        // ("pre-verification") and never gates a booking. The
        // minimum_age_by_category config below still powers the storefront's
        // info-only age disclosure (vehicle detail requirements + checkout
        // warning), but nothing blocks a booking on it.
        $this->registerFilamentResource();
    }

    /**
     * Registers this plugin's Filament resource into the admin panel
     * without core ever referencing this plugin's namespace — the plugin
     * registers itself into the already-configured default panel here,
     * rather than the panel's own provider needing a discovery path or
     * class reference into `plugins/*`.
     */
    private function registerFilamentResource(): void
    {
        $panel = Filament::getDefaultPanel();

        $panel->resources([DriverVerificationResource::class]);

        Route::name('filament.')
            ->group(function () use ($panel): void {
                Route::middleware($panel->getMiddleware())
                    ->name($panel->getId().'.')
                    ->prefix($panel->getPath())
                    ->group(function () use ($panel): void {
                        Route::middleware($panel->getAuthMiddleware())
                            ->group(function () use ($panel): void {
                                Route::middleware([])
                                    ->prefix('')
                                    ->group(function () use ($panel): void {
                                        DriverVerificationResource::registerRoutes($panel);
                                    });
                            });
                    });
            });
    }
}
