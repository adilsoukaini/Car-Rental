<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared fleet-catalog query building — the single source of truth for "how
 * the public vehicle listing is assembled" so the storefront
 * (Plugins\FleetManagement\Http\Controllers\VehicleController) and the mobile
 * JSON API (App\Http\Controllers\Api\VehicleController) can never drift apart.
 *
 * This class deliberately contains no new business logic — it wires the
 * existing building blocks together:
 *   - `available` status guard
 *   - free-text make/model search
 *   - VehicleFilterRegistry (category, transmission, location, ...)
 *   - VehicleSortRegistry (price_asc/price_desc/name_asc, ...)
 *   - the `vehicle.listQuery` filter pipeline (vehicle-media's eager-load
 *     pipe, etc.)
 *   - the batch-loaded approved-review summary (rule 8 — one aggregate query)
 *
 * Both controllers paginate the returned Builder themselves (the web page
 * renders a paginator; the API serializes the same paginator as JSON), so the
 * page size stays a per-consumer concern.
 */
class VehicleCatalogService
{
    /**
     * Build the public fleet-listing query from a request's filter/sort/search
     * input. Does NOT paginate — callers paginate the returned Builder.
     *
     * @return Builder<Vehicle>
     */
    public function fleetQuery(Request $request): Builder
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

        // Approved-review count + average rating in one aggregate query (rule 8).
        // No-ops (and hides the card snippet) when the reviews plugin is disabled.
        $query = $query->withReviewSummary();

        return $query;
    }

    /**
     * Resolve the vehicle-detail context shared by the web detail page and the
     * mobile API detail endpoint: the eager-loaded location plus the
     * reviews/gallery/attributes/recommendations filter slots. The vehicle is
     * mutated in place (loadMissing) and the detail arrays are returned.
     *
     * @return array{
     *     reviewsData: array<string, mixed>,
     *     galleryImages: array<int, mixed>,
     *     attributes: array<int, mixed>,
     *     recommendations: array<int, mixed>,
     * }
     */
    public function detail(Vehicle $vehicle): array
    {
        $vehicle->loadMissing('location');

        // Approved reviews + rating (reviews plugin's `vehicle.reviews` filter).
        $reviewsData = FilterRegistry::applyWithContext(
            'vehicle.reviews',
            ['vehicleId' => $vehicle->id, 'averageRating' => 0.0, 'reviewCount' => 0, 'reviews' => []],
            [Vehicle::class => $vehicle],
        );

        // Real gallery from the vehicle-media plugin (registered on the
        // vehicle.gallery filter). Empty array when the plugin is disabled.
        $galleryImages = FilterRegistry::applyWithContext(
            'vehicle.gallery',
            [],
            [Vehicle::class => $vehicle],
        );

        // Custom spec attributes (GPS, insurance type, mileage limit, ...)
        // resolved via the vehicle.attributes filter. Empty array when the
        // plugin is disabled or the vehicle carries no values.
        $attributes = FilterRegistry::applyWithContext(
            'vehicle.attributes',
            [],
            [Vehicle::class => $vehicle],
        );

        // Similar-vehicle recommendations (vehicle.recommendations filter).
        // Empty array when the plugin is disabled.
        $recommendations = FilterRegistry::applyWithContext(
            'vehicle.recommendations',
            [],
            [Vehicle::class => $vehicle],
        );

        return compact('reviewsData', 'galleryImages', 'attributes', 'recommendations');
    }
}
