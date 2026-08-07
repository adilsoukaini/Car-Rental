<?php

declare(strict_types=1);

namespace Plugins\VehicleAttributes;

use App\Core\Support\FilterRegistry;
use App\Core\Support\VehicleResourceExtension;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Plugins\VehicleAttributes\Filament\Forms\VehicleAttributesFormSection;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\CreateVehicleAttributeDefinition;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\EditVehicleAttributeDefinition;
use Plugins\VehicleAttributes\Filament\Resources\VehicleAttributeDefinitionResource\Pages\ListVehicleAttributeDefinitions;
use Plugins\VehicleAttributes\Pipes\GetVehicleAttributesPipe;

class VehicleAttributesServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Resolve a vehicle's custom attributes for the detail page. Context
        // (Vehicle) is injected via FilterRegistry::applyWithContext() at call
        // time — neither the fleet plugin nor core references this plugin.
        FilterRegistry::register('vehicle.attributes', GetVehicleAttributesPipe::class);

        // Add a dynamically-built "Custom Attributes" section to
        // VehicleResource's create/edit form. The callback returns the
        // section component; core invokes it without ever importing this
        // plugin's class (Hard Rule 1).
        VehicleResourceExtension::addFormSection(
            fn (): array => [VehicleAttributesFormSection::make()],
        );

        $this->registerFilamentResource();

        $this->registerLivewirePages();
    }

    /**
     * Register this resource's page classes as Livewire components.
     *
     * Core-owned resources are registered by Filament's own discovery
     * (AdminPanelProvider's discoverResources/discoverPages), which calls
     * Livewire::component() for every page. A plugin that adds a resource
     * via $panel->resources([...]) gets no such registration — the initial
     * render works (the page class is used directly), but a subsequent
     * Livewire update request resolves the component by its kebab-case
     * name, which is never registered → ComponentNotFoundException (shown
     * as "419 Page Expired"). Same manual-registration pattern already
     * proven for the vehicle-media plugin's relation manager.
     */
    private function registerLivewirePages(): void
    {
        foreach ([
            ListVehicleAttributeDefinitions::class,
            CreateVehicleAttributeDefinition::class,
            EditVehicleAttributeDefinition::class,
        ] as $pageClass) {
            Livewire::component(
                app(ComponentRegistry::class)->getName($pageClass),
                $pageClass,
            );
        }
    }

    /**
     * Same self-registration pattern as reviews/driver-verification — the
     * plugin registers its own resource into the already-configured default
     * panel, so core's AdminPanelProvider never references this namespace.
     */
    private function registerFilamentResource(): void
    {
        $panel = Filament::getDefaultPanel();

        $panel->resources([VehicleAttributeDefinitionResource::class]);

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
                                        VehicleAttributeDefinitionResource::registerRoutes($panel);
                                    });
                            });
                    });
            });
    }
}
