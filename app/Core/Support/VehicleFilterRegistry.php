<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Contracts\VehicleFilterProvider;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registry of every available fleet-listing filter.
 *
 * Mirror of the e-commerce project's ProductFilterRegistry. Each registered
 * VehicleFilterProvider knows both how to render itself in the FilterBar and
 * how to constrain the Vehicle query. `applyAll()` applies every registered
 * filter that has a non-empty value in the request input — the controller
 * just passes `$request->all()` through and never touches a WHERE clause
 * itself for these filters.
 *
 * (The design doc's admin-control layer — fleet_control_settings gating which
 * filters are enabled — is a separate, deliberately deferred phase. Until it
 * exists every registered filter is enabled by default, exactly as the doc
 * specifies for the pre-admin-control state.)
 *
 * NOTE: static state survives across Application boots within the same PHP
 * process (every test method creates a fresh Application but reuses the same
 * process), so PluginManager::boot() calls flush() before re-registering —
 * same accumulation guard as FilterRegistry/SlotRegistry.
 */
class VehicleFilterRegistry
{
    /** @var array<string, VehicleFilterProvider> */
    protected static array $filters = [];

    public static function register(VehicleFilterProvider $filter): void
    {
        static::$filters[$filter->id()] = $filter;
    }

    /**
     * All registered filters, in registration order.
     *
     * @return array<string, VehicleFilterProvider>
     */
    public static function all(): array
    {
        return static::$filters;
    }

    /**
     * Apply every registered filter that has a non-empty value in $input.
     *
     * @param  Builder<Vehicle>  $query
     * @param  array<string, mixed>  $input
     * @return Builder<Vehicle>
     */
    public static function applyAll(Builder $query, array $input): Builder
    {
        foreach (static::$filters as $id => $filter) {
            if (array_key_exists($id, $input) && $input[$id] !== null && $input[$id] !== '') {
                $query = $filter->apply($query, $input[$id]);
            }
        }

        return $query;
    }

    /**
     * Clear all registered filters — called at the top of PluginManager::boot()
     * so each boot cycle starts from a genuinely clean registry state.
     */
    public static function flush(): void
    {
        static::$filters = [];
    }
}
