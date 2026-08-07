<?php

declare(strict_types=1);

namespace Plugins\FleetManagement\Http\Controllers;

use App\Core\Support\FilterRegistry;
use App\Core\Support\VehicleFilterRegistry;
use App\Core\Support\VehicleSortRegistry;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
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
        $query = Vehicle::where('status', 'available');

        // Free-text search by make/model — deliberately kept out of the
        // filter registry: it's a text search, not a selectable FilterBar
        // filter, and the task's server-side spec handles it explicitly.
        $search = $request->string('search')->trim()->toString();
        if ($search !== '') {
            // Case-insensitive (LOWER on both sides) so ?search=toyota matches
            // a stored 'Toyota' — works identically on SQLite and Postgres.
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(make) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(model) LIKE ?', [$needle]);
            });
        }

        // Registered select filters — each provider knows its own WHERE clause.
        $query = VehicleFilterRegistry::applyAll($query, $request->all());

        // Registered sort options. No sort param means the DB's natural order
        // (the frontend's "Default" option) — a sort is only applied when the
        // request actually asks for one.
        $requestedSort = $request->string('sort')->trim()->toString();
        $sort = $requestedSort !== '' ? VehicleSortRegistry::resolveActive($requestedSort) : null;
        if ($sort !== null) {
            $query = $sort->apply($query);
        }

        $query = $query->with('location');
        $query = FilterRegistry::apply('vehicle.listQuery', $query);

        $vehicles = $query->paginate(12)->withQueryString();

        // Only expose what's registered — the frontend renders every entry
        // generically, so a newly-registered filter/sort appears with zero
        // frontend changes.
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

        // The currently-active values, so the FilterBar and SearchBox can
        // pre-select/reflect what the URL says.
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

        $vehicle->loadMissing('location');

        $reviewsData = FilterRegistry::applyWithContext(
            'vehicle.reviews',
            ['vehicleId' => $vehicle->id, 'averageRating' => 0.0, 'reviewCount' => 0, 'reviews' => []],
            [Vehicle::class => $vehicle],
        );

        // Real gallery from the vehicle-media plugin (registered on the
        // vehicle.gallery filter). An empty array when the plugin is disabled
        // or the vehicle has no uploaded photos — the page falls back to the
        // VehiclePlaceholderIcon in that case.
        $galleryImages = FilterRegistry::applyWithContext(
            'vehicle.gallery',
            [],
            [Vehicle::class => $vehicle],
        );

        // Custom spec attributes (GPS, insurance type, mileage limit, ...)
        // resolved via the vehicle.attributes filter (registered by the
        // vehicle-attributes plugin). Empty array when the plugin is disabled
        // or the vehicle carries no values — the detail page hides the
        // attributes section in that case.
        $attributes = FilterRegistry::applyWithContext(
            'vehicle.attributes',
            [],
            [Vehicle::class => $vehicle],
        );

        // Resolved via the vehicle.recommendations filter (registered by the
        // recommendations plugin). Empty array when the plugin is disabled.
        $recommendations = FilterRegistry::applyWithContext(
            'vehicle.recommendations',
            [],
            [Vehicle::class => $vehicle],
        );

        return Inertia::render('Vehicles/Show', [
            'vehicle' => $vehicle,
            'galleryImages' => $galleryImages,
            'reviewsData' => $reviewsData,
            'attributes' => $attributes,
            'recommendations' => $recommendations,
        ]);
    }
}
