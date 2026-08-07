<?php

declare(strict_types=1);

namespace Plugins\Reviews;

use App\Core\Support\FilterRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Plugins\Reviews\Filament\Resources\Reviews\Pages\ListReviews;
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

        // Review DISPLAY no longer lives here — it moved from a SlotRegistry
        // entry (vehicle.detailWidgets -> Widgets/VehicleReviews) to the
        // core-owned `reviewDisplay` layout variant (LayoutVariantRegistry,
        // registered in AppServiceProvider), so an admin can switch between
        // the card-list and compact components. This plugin still owns the
        // review DATA (the vehicle.reviews filter above), the store route,
        // and the Filament resource.

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

                                // Register Livewire page components so Filament
                                // actions (Approve/Reject) don't 419 on plugin-owned
                                // resources — same late-registration pattern already
                                // proven for vehicle-media's relation manager.
                                Livewire::component(
                                    app(ComponentRegistry::class)->getName(ListReviews::class),
                                    ListReviews::class,
                                );
                            });
                    });
            });
    }
}
