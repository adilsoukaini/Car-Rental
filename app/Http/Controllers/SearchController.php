<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Instant autocomplete suggestions for the storefront SearchBox.
     *
     * Searches vehicles through Laravel Scout (database driver), which
     * performs a case-insensitive ILIKE/LIKE match against the columns listed
     * in Vehicle::toSearchableArray() (id, make, model, category, year). Only
     * available vehicles are suggested — a suggestion must always land on a
     * page that returns 200, never a 404 detail page.
     *
     * The primary image is batch-loaded in one query when the vehicle-media
     * plugin has registered the dynamic `primaryImage` relation (rule 8); when
     * it hasn't, `imageUrl` is simply null and the frontend falls back to a
     * placeholder icon. Core never references the plugin's namespace — it only
     * checks whether the model's dynamic-relation registry knows the key.
     *
     * Response shape is a plain JSON array (max 5), matching what the SearchBox
     * dropdown consumes directly.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $vehicles = Vehicle::search($query)
            ->where('status', 'available')
            ->take(5)
            ->get();

        // Batch-load each result's primary image in one query (rule 8) — but
        // only when the vehicle-media plugin actually registered the relation.
        if ((new Vehicle)->relationResolver(Vehicle::class, 'primaryImage') !== null) {
            $vehicles->load('primaryImage');
        }

        return response()->json(
            $vehicles->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'category' => $vehicle->category,
                'imageUrl' => $vehicle->primaryImage?->url ?? null,
            ])->all(),
        );
    }
}
