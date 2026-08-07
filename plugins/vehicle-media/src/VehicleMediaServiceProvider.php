<?php

declare(strict_types=1);

namespace Plugins\VehicleMedia;

use App\Core\Support\FilterRegistry;
use App\Core\Support\VehicleResourceExtension;
use App\Models\Vehicle;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Plugins\VehicleMedia\Filament\RelationManagers\VehicleImagesRelationManager;
use Plugins\VehicleMedia\Models\VehicleImage;
use Plugins\VehicleMedia\Pipes\EagerLoadPrimaryImagePipe;
use Plugins\VehicleMedia\Pipes\GetVehicleGalleryPipe;

class VehicleMediaServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Resolve a vehicle's real gallery — context (Vehicle) is injected
        // via FilterRegistry::applyWithContext() at call time.
        FilterRegistry::register('vehicle.gallery', GetVehicleGalleryPipe::class);

        // Adds with('primaryImage') to the fleet-listing query so every
        // card's image is loaded in one batch, not per-vehicle (rule 8).
        FilterRegistry::register('vehicle.listQuery', EagerLoadPrimaryImagePipe::class);

        // Expose 'images'/'primaryImage' relationships on Vehicle without
        // touching the core model file (Hard Rule 1) — same pattern as
        // product-media's Product::resolveRelationUsing() in the source
        // e-commerce project.
        Vehicle::resolveRelationUsing('images', function (Vehicle $vehicle) {
            return $vehicle->hasMany(VehicleImage::class);
        });

        Vehicle::resolveRelationUsing('primaryImage', function (Vehicle $vehicle) {
            return $vehicle->hasOne(VehicleImage::class)
                ->where('is_primary', true)
                ->orderBy('sort_order');
        });

        // Adds the relation manager to VehicleResource via the core
        // extension registry — core never imports this plugin's class.
        VehicleResourceExtension::addRelationManager(VehicleImagesRelationManager::class);

        // FilamentServiceProvider::boot() runs before plugin providers and
        // calls registerLivewireComponents(), which iterates
        // VehicleResource::getRelationManagers() while the extension
        // registry is still empty. Manually register the component so
        // Livewire can resolve it during the request — same late-
        // registration pattern already proven in this project for
        // plugin-owned Filament resources (see driver-verification's
        // ServiceProvider), applied here to a relation manager instead.
        Livewire::component(
            app(ComponentRegistry::class)->getName(VehicleImagesRelationManager::class),
            VehicleImagesRelationManager::class,
        );
    }
}
