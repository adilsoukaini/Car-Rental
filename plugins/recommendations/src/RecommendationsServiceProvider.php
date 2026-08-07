<?php

declare(strict_types=1);

namespace Plugins\Recommendations;

use App\Core\Support\FilterRegistry;
use Illuminate\Support\ServiceProvider;
use Plugins\Recommendations\Pipes\GetVehicleRecommendationsPipe;

class RecommendationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/recommendations.php', 'recommendations');
    }

    public function boot(): void
    {
        // Registered on vehicle.recommendations — fleet-management's
        // VehicleController::show() calls applyWithContext() with the
        // current Vehicle bound into the container. Core/fleet-management
        // never reference this plugin; they only know the named filter.
        FilterRegistry::register('vehicle.recommendations', GetVehicleRecommendationsPipe::class);
    }
}
