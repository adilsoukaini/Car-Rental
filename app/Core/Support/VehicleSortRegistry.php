<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Contracts\VehicleSortOption;

/**
 * Registry of every available fleet-listing sort option.
 *
 * Mirror of the e-commerce project's ProductSortRegistry. The design doc's
 * admin-control layer (fleet_control_settings gating which sorts are enabled)
 * is a separate, deliberately deferred phase — until it exists every
 * registered sort is available by default.
 *
 * NOTE: static state survives across Application boots within the same PHP
 * process, so PluginManager::boot() calls flush() before re-registering —
 * same accumulation guard as FilterRegistry/SlotRegistry.
 */
class VehicleSortRegistry
{
    /** @var array<string, VehicleSortOption> */
    protected static array $sorts = [];

    public static function register(VehicleSortOption $sort): void
    {
        static::$sorts[$sort->id()] = $sort;
    }

    /**
     * All registered sorts, in registration order.
     *
     * @return array<string, VehicleSortOption>
     */
    public static function all(): array
    {
        return static::$sorts;
    }

    /**
     * Look up a sort by ID, or null if not registered.
     */
    public static function get(string $id): ?VehicleSortOption
    {
        return static::$sorts[$id] ?? null;
    }

    /**
     * Resolve a sort by the requested ID, falling back to the first registered
     * sort when the requested one is unknown. Guarantees the controller always
     * has a valid sort to apply for a non-empty requested ID.
     */
    public static function resolveActive(string $requestedId): VehicleSortOption
    {
        foreach (static::$sorts as $sort) {
            if ($sort->id() === $requestedId) {
                return $sort;
            }
        }

        return static::$sorts[array_key_first(static::$sorts)];
    }

    /**
     * Clear all registered sorts — called at the top of PluginManager::boot()
     * so each boot cycle starts from a genuinely clean registry state.
     */
    public static function flush(): void
    {
        static::$sorts = [];
    }
}
