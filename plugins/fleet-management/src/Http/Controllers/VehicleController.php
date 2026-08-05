<?php

declare(strict_types=1);

namespace Plugins\FleetManagement\Http\Controllers;

use App\Core\Support\FilterRegistry;
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

    public function show(Vehicle $vehicle): Response
    {
        abort_if($vehicle->status !== 'available', 404);

        $vehicle->loadMissing('location');

        return Inertia::render('Vehicles/Show', [
            'vehicle' => $vehicle,
        ]);
    }
}
