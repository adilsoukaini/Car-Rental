<?php

declare(strict_types=1);

namespace App\Core\Filters;

use App\Core\Contracts\VehicleFilterProvider;
use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fleet filter by pickup/return city (the vehicle's current location).
 *
 * Options are the distinct cities of all active locations — the storefront's
 * FilterBar lets a visitor narrow the fleet to vehicles physically homed at a
 * given city's branches. The select is driven from real Location data (not a
 * hardcoded list) so adding a new city as a Location row surfaces it here
 * automatically, and deactivating a location drops its city from the options.
 *
 * Apply joins the locations table (the task's explicit mechanism) and filters
 * on `locations.city`. The select is qualified to `vehicles.*` so the joined
 * rows never overwrite the vehicles' own columns (PDO resolves duplicate
 * column names like `id` to the last-seen one — without the qualified select,
 * a joined `locations.id` would clobber the vehicle id on hydration).
 */
class VehicleLocationFilter implements VehicleFilterProvider
{
    public function id(): string
    {
        return 'location';
    }

    public function label(): string
    {
        return 'Location';
    }

    public function uiType(): string
    {
        return 'select';
    }

    /** @return array<int, array{value: mixed, label: string}> */
    public function options(): array
    {
        return Location::where('is_active', true)
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->map(fn (string $city) => [
                'value' => $city,
                'label' => $city,
            ])
            ->values()
            ->all();
    }

    /** @param Builder<Vehicle> $query @return Builder<Vehicle> */
    public function apply(Builder $query, mixed $value): Builder
    {
        // Case-insensitive so a hand-typed ?location=CASABLANCA matches the
        // stored 'Casablanca' — the same rationale as VehicleCategoryFilter.
        return $query->join('locations', 'vehicles.location_id', '=', 'locations.id')
            ->select('vehicles.*')
            ->whereRaw('LOWER(locations.city) = ?', [mb_strtolower((string) $value)]);
    }
}
