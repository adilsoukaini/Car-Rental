<?php

declare(strict_types=1);

namespace Plugins\BookingEngine;

use App\Core\Events\VehicleReturned;
use App\Core\Support\FilterRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Plugins\BookingEngine\Console\Commands\ReleaseExpiredBookingHolds;
use Plugins\BookingEngine\Filters\CoreAvailabilityCheckPipe;
use Plugins\BookingEngine\Filters\CoreCancellationPolicyPipe;
use Plugins\BookingEngine\Filters\CoreDepositPipe;
use Plugins\BookingEngine\Filters\CoreDurationDiscountPipe;
use Plugins\BookingEngine\Listeners\RelocateVehicleOnReturn;

class BookingEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/booking-engine.php', 'booking-engine');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/booking-engine.php');

        FilterRegistry::register('booking.availabilityCheck', CoreAvailabilityCheckPipe::class);

        // Order matters: CoreDepositPipe reads $breakdown->subtotal, which
        // CoreDurationDiscountPipe must set first.
        FilterRegistry::register('booking.priceCalculation', CoreDurationDiscountPipe::class, 10);
        FilterRegistry::register('booking.priceCalculation', CoreDepositPipe::class, 20);

        FilterRegistry::register('booking.cancellationPolicy', CoreCancellationPolicyPipe::class);

        Event::listen(VehicleReturned::class, RelocateVehicleOnReturn::class);

        $this->commands([ReleaseExpiredBookingHolds::class]);
    }
}
