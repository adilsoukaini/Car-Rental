<?php

declare(strict_types=1);

namespace Plugins\Promotions;

use App\Core\Events\BookingConfirmed;
use App\Core\Support\FilterRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\Promotions\Filament\Resources\PromoCodeResource;
use Plugins\Promotions\Filters\PromoCodePipe;
use Plugins\Promotions\Listeners\IncrementPromoCodeUsage;

class PromotionsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Priority 17: after CoreDurationDiscountPipe (10) and
        // CoreLoyaltyDiscountPipe (15) have set the base subtotal, before
        // CoreDepositPipe (20) so the deposit is a percentage of the final,
        // promo-discounted subtotal. See PromoCodePipe's docblock.
        FilterRegistry::register('booking.priceCalculation', PromoCodePipe::class, 17);

        Event::listen(BookingConfirmed::class, IncrementPromoCodeUsage::class);

        $this->registerFilamentResource();
    }

    /**
     * Same self-registration pattern as reviews/driver-verification — the
     * plugin registers its own resource into the already-configured default
     * panel, so core's AdminPanelProvider never references this plugin's
     * namespace.
     */
    private function registerFilamentResource(): void
    {
        $panel = Filament::getDefaultPanel();

        $panel->resources([PromoCodeResource::class]);

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
                                        PromoCodeResource::registerRoutes($panel);
                                    });
                            });
                    });
            });
    }
}
