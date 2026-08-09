<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Support\VehicleCatalogService;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API fleet endpoints for the mobile app. Deliberately thin: all query
 * building/decorating delegates to VehicleCatalogService (the same service the
 * storefront's fleet controller uses), so the web page and the API can never
 * drift — the API serializes exactly what the Inertia pages pass to the React
 * screens (see the mobile app's lib/api.ts Vehicle/VehicleDetail shapes).
 */
class VehicleController extends Controller
{
    public function __construct(private readonly VehicleCatalogService $catalog) {}

    public function index(Request $request): JsonResponse
    {
        // The mobile app's VehicleFilters supports a per_page param — honor it
        // within sane bounds. Laravel's paginate() ignores the query-string
        // per_page when an explicit page size is passed, so read it ourselves.
        $perPage = min(max((int) $request->input('per_page', 12), 1), 50);

        $vehicles = $this->catalog->fleetQuery($request)->paginate($perPage);

        return response()->json($vehicles);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        abort_if($vehicle->status !== 'available', 404);

        $detail = $this->catalog->detail($vehicle);

        return response()->json([
            'vehicle' => $vehicle,
            'galleryImages' => $detail['galleryImages'],
            'reviewsData' => $detail['reviewsData'],
            'attributes' => $detail['attributes'],
            'recommendations' => $detail['recommendations'],
        ]);
    }
}
