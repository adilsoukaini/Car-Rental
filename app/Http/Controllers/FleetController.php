<?php

namespace App\Http\Controllers;

use App\Core\Support\VehicleCatalogService;
use App\Core\Support\VehicleFilterRegistry;
use App\Core\Support\VehicleSortRegistry;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Core-owned fleet controller. Mirrors the fleet-management plugin's
 * VehicleController but lives in the App namespace so it doesn't depend
 * on the plugin autoload. Used as a fallback when the plugin provider
 * can't be auto-discovered (e.g. in Docker/Cloud Run builds where
 * Composer path-repo symlinks are broken by COPY).
 */
class FleetController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $requestedSort = $request->string('sort')->trim()->toString();
        $sort = $requestedSort !== '' ? VehicleSortRegistry::resolveActive($requestedSort) : null;

        $vehicles = app(VehicleCatalogService::class)
            ->fleetQuery($request)
            ->paginate(12)
            ->withQueryString();

        $availableFilters = collect(VehicleFilterRegistry::enabled())
            ->map(fn ($filter) => [
                'id' => $filter->id(),
                'label' => $filter->label(),
                'uiType' => $filter->uiType(),
                'options' => $filter->options(),
            ])
            ->values()
            ->all();

        $availableSorts = collect(VehicleSortRegistry::all())
            ->map(fn ($sortOption) => [
                'id' => $sortOption->id(),
                'label' => $sortOption->label(),
            ])
            ->values()
            ->all();

        $activeFilters = collect(VehicleFilterRegistry::enabled())
            ->mapWithKeys(fn ($filter) => [$filter->id() => $request->string($filter->id())->toString()])
            ->filter(fn (string $value) => $value !== '')
            ->all();

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
            'search' => $search,
            'availableFilters' => $availableFilters,
            'availableSorts' => $availableSorts,
            'currentSort' => $sort?->id() ?? '',
            'activeFilters' => $activeFilters,
        ]);
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        // The plugin's VehicleController::show() loads much more data (gallery,
        // reviews, attributes, recommendations) via pipeline filters. For a
        // minimal working fallback we just load the vehicle itself.
        $vehicle->load(['location']);

        return Inertia::render('Vehicles/Show', [
            'vehicle' => $vehicle,
            'reviewsData' => ['reviews' => [], 'averageRating' => null, 'totalReviews' => 0],
            'attributes' => [],
            'recommendations' => [],
        ]);
    }
}
