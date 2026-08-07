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
     * **Meilisearch-first, database-fallback.** Tries Scout (Meilisearch when
     * SCOUT_DRIVER=meilisearch) first. If it fails for any reason (container
     * down, connection refused, index missing, timeout), falls back to a raw
     * database ILIKE/LIKE query — the search box always works regardless of
     * Meilisearch availability.
     *
     * Only available vehicles are suggested. Response shape is a plain JSON
     * array (max 5), matching what the SearchBox dropdown consumes directly.
     * The primary image is batch-loaded in one query (rule 8).
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        // Meilisearch-first, graceful fallback to database.
        try {
            $vehicles = Vehicle::search($query)
                ->where('status', 'available')
                ->take(5)
                ->get();
        } catch (\Throwable) {
            // Scout/Meilisearch is unavailable — fall back to a raw database
            // LIKE query. Case-insensitive on both SQLite and Postgres via
            // LOWER(). This is deliberately NOT the fleet page's full text
            // search — it's the autocomplete dropdown only.
            $needle = '%'.mb_strtolower($query).'%';
            $vehicles = Vehicle::where('status', 'available')
                ->where(function ($q) use ($needle) {
                    $q->whereRaw('LOWER(make) LIKE ?', [$needle])
                      ->orWhereRaw('LOWER(model) LIKE ?', [$needle]);
                })
                ->take(5)
                ->get();
        }

        // Batch-load primary images in one query (rule 8).
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
