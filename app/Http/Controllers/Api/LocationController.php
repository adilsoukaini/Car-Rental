<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

/**
 * Public active-location list for the mobile app's pickup/return pickers.
 * Mirrors the query the storefront checkout page and homepage use — only
 * `is_active` locations, ordered city then name.
 */
class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('city')
            ->orderBy('name')
            ->get(['id', 'name', 'address_line', 'city', 'country', 'latitude', 'longitude']);

        return response()->json($locations);
    }
}
