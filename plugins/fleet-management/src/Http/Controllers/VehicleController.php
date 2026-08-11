<?php

declare(strict_types=1);

namespace Plugins\FleetManagement\Http\Controllers;

use App\Core\Support\VehicleCatalogService;
use App\Core\Support\VehicleFilterRegistry;
use App\Core\Support\VehicleSortRegistry;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    /**
     * Public fleet listing — server-side filtering/sorting.
     *
     * Search (free-text make/model), the registered VehicleFilterRegistry
     * filters (category, transmission), and the active VehicleSortRegistry
     * sort (price_asc/price_desc/name_asc) are all applied as WHERE/ORDER BY
     * clauses BEFORE pagination, so filtering works across the whole fleet,
     * not just the current page. The vehicle.listQuery filter still composes
     * here (vehicle-media's eager-load pipe, etc.) — independent of and
     * stacking cleanly with this new filter/sort layer.
     *
     * The frontend renders filter/sort controls generically from the
     * availableFilters/availableSorts props, and reflects the current values
     * back through the URL query string (search/sort/category/transmission).
     */
    public function index(Request $request): Response
    {
        try {
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
        } catch (\Throwable $e) {
            Log::error('VehicleController::index failed', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * `reviewsData` is resolved via the `vehicle.reviews` filter (registered
     * by the reviews plugin) and shared as a direct page prop — the vehicle
     * detail page renders it through the `reviewDisplay` layout variant
     * (LayoutVariantRegistry), which swaps between the card-list and compact
     * review components. fleet-management never references the reviews
     * plugin directly; it only knows the named filter exists.
     */
    public function show(Vehicle $vehicle): Response
    {
        abort_if($vehicle->status !== 'available', 404);

        // Detail context (location + reviews/gallery/attributes/recommendations
        // slots) is resolved by the shared VehicleCatalogService — the same
        // service the mobile JSON API uses, so the shapes never drift.
        $detail = app(VehicleCatalogService::class)->detail($vehicle);

        // Per-page SEO — overrides the shared `seo` default shared by
        // HandleInertiaRequests so this page's og:title is vehicle-specific
        // (page props merge over shared props). The price formatting mirrors
        // Show.tsx's `Number(vehicle.daily_rate).toFixed(0)` ("550.00" → "550").
        $price = (string) round((float) $vehicle->daily_rate);

        return Inertia::render('Vehicles/Show', [
            'vehicle' => $vehicle,
            'galleryImages' => $detail['galleryImages'],
            'reviewsData' => $detail['reviewsData'],
            'attributes' => $detail['attributes'],
            'recommendations' => $detail['recommendations'],
            'seo' => [
                'title' => "{$vehicle->make} {$vehicle->model} — Rent from {$price} MAD/day",
                'description' => "Rent a {$vehicle->make} {$vehicle->model} for {$price} MAD/day.",
            ],
        ]);
    }
}
