<?php

declare(strict_types=1);

namespace Plugins\Reviews;

use App\Core\Support\FilterRegistry;
use App\Core\Support\SlotRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\Reviews\Filament\Resources\Reviews\ReviewResource;
use Plugins\Reviews\Filters\GetVehicleReviewsPipe;

class ReviewsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/reviews.php');

        FilterRegistry::register('vehicle.reviews', GetVehicleReviewsPipe::class);

        // The first real slot registered into a PLUGIN-owned page rather
        // than a core one (account.dashboardWidgets, the first real slot
        // overall, renders into core's Profile/Edit.tsx) — proves
        // SlotRegistry works for fleet-management's Vehicles/Show.tsx too,
        // without fleet-management ever referencing this plugin directly.
        SlotRegistry::register('vehicle.detailWidgets', 'Reviews/VehicleReviews');

        $this->registerFilamentResource();
    }

    /**
     * Same self-registration pattern as driver-verification's
     * DriverVerificationServiceProvider — the plugin registers its own
     * resource into the already-configured default panel, so core's
     * AdminPanelProvider never references this plugin's namespace.
     */
    private function registerFilamentResource(): void
    {
        $panel = Filament::getDefaultPanel();

        $panel->resources([ReviewResource::class]);

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
                                        ReviewResource::registerRoutes($panel);
                                    });
                            });
                    });
            });
    }
}
