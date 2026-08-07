<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Contracts\VehicleFilterProvider;
use App\Models\CatalogControlSetting;
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
 * Admin control: the catalog_control_settings table (CatalogControlSetting
 * model) gates which filters are enabled. Absence of a row means enabled — so
 * a fresh install behaves exactly as the doc specifies for the pre-admin-
 * control state. The CatalogControlSettings admin page upserts those rows;
 * applyAll()/enabled() consult them.
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
     * All registered filters, in registration order — including disabled ones,
     * so the admin page can render a toggle for every filter.
     *
     * @return array<string, VehicleFilterProvider>
     */
    public static function all(): array
    {
        return static::$filters;
    }

    /**
     * Only the filters still enabled in catalog_control_settings, in
     * registration order. The storefront (FilterBar props, applyAll,
     * activeFilters) uses this so a disabled filter is neither shown nor
     * applied.
     *
     * @return array<string, VehicleFilterProvider>
     */
    public static function enabled(): array
    {
        return array_filter(
            static::$filters,
            fn (VehicleFilterProvider $filter) => static::isEnabled($filter->id()),
        );
    }

    /**
     * Apply every *enabled* registered filter that has a non-empty value in
     * $input. Disabled filters are skipped entirely.
     *
     * @param  Builder<Vehicle>  $query
     * @param  array<string, mixed>  $input
     * @return Builder<Vehicle>
     */
    public static function applyAll(Builder $query, array $input): Builder
    {
        foreach (static::$filters as $id => $filter) {
            if (! static::isEnabled($id)) {
                continue;
            }

            if (array_key_exists($id, $input) && $input[$id] !== null && $input[$id] !== '') {
                $query = $filter->apply($query, $input[$id]);
            }
        }

        return $query;
    }

    protected static function isEnabled(string $filterId): bool
    {
        return CatalogControlSetting::isControlEnabled(CatalogControlSetting::TYPE_FILTER, $filterId);
    }

    /**
     * Clear all registered filters — called at the top of PluginManager::boot()
     * so each boot cycle starts from a genuinely clean registry state. Also
     * drops the shared enabled-map cache so stale settings never survive a
     * boot boundary (tests create a fresh Application — and a fresh DB — per
     * test method within the same PHP process).
     */
    public static function flush(): void
    {
        static::$filters = [];
        CatalogControlSetting::resetCache();
    }
}
