<?php

declare(strict_types=1);

namespace Plugins\FleetManagement\Http\Controllers;

use App\Core\Support\FilterRegistry;
use App\Core\Support\SlotRegistry;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    /**
     * Public fleet listing — applies the vehicle.listQuery filter so other
     * plugins (pricing-rules, insurance-addons, etc.) can augment the query
     * without touching this controller.
     */
    public function index(Request $request): Response
    {
        $query = Vehicle::where('status', 'available')->with('location');

        $query = FilterRegistry::apply('vehicle.listQuery', $query);

        $vehicles = $query->paginate(12)->withQueryString();

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * `detailWidgets` is the first Slot registered into a PLUGIN-owned page
     * (this one) rather than a core page — fleet-management never
     * references the reviews plugin directly; it only knows the named
     * slot exists, exactly like core's `account.dashboardWidgets`.
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

        return Inertia::render('Vehicles/Show', [
            'vehicle' => $vehicle,
            'galleryImages' => $galleryImages,
            'detailWidgets' => SlotRegistry::render('vehicle.detailWidgets', [
                'vehicleId' => $vehicle->id,
                'reviewsData' => $reviewsData,
            ]),
        ]);
    }
}
