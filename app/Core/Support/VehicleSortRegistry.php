<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Contracts\VehicleSortOption;
use App\Models\CatalogControlSetting;

/**
 * Registry of every available fleet-listing sort option.
 *
 * Mirror of the e-commerce project's ProductSortRegistry. Admin control via
 * the catalog_control_settings table (CatalogControlSetting model): absence
 * of a row means enabled, so every registered sort is available by default
 * until the CatalogControlSettings admin page disables one. resolveActive()/
 * enabled() consult the table.
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
     * All registered sorts, in registration order — including disabled ones,
     * so the admin page can render a toggle for every sort.
     *
     * @return array<string, VehicleSortOption>
     */
    public static function all(): array
    {
        return static::$sorts;
    }

    /**
     * Only the sorts still enabled in catalog_control_settings, in
     * registration order. The storefront sort dropdown uses this so a
     * disabled sort is neither shown nor applied.
     *
     * @return array<string, VehicleSortOption>
     */
    public static function enabled(): array
    {
        return array_filter(
            static::$sorts,
            fn (VehicleSortOption $sort) => static::isEnabled($sort->id()),
        );
    }

    /**
     * Look up a sort by ID, or null if not registered.
     */
    public static function get(string $id): ?VehicleSortOption
    {
        return static::$sorts[$id] ?? null;
    }

    /**
     * Resolve a sort by the requested ID among the *enabled* sorts only.
     * Returns null when the requested sort is registered but disabled — the
     * controller then applies no sort (default order), rather than silently
     * substituting the first enabled sort, which could invert the user's
     * intent (e.g. a disabled price_asc falling back to price_desc). An
     * unknown ID keeps the pre-existing fallback to the first enabled sort.
     */
    public static function resolveActive(string $requestedId): ?VehicleSortOption
    {
        $enabled = static::enabled();

        foreach ($enabled as $sort) {
            if ($sort->id() === $requestedId) {
                return $sort;
            }
        }

        // Registered but disabled — honor the disable, don't substitute.
        if (isset(static::$sorts[$requestedId])) {
            return null;
        }

        // Unknown requested ID — fall back to the first enabled sort.
        $first = array_key_first($enabled);

        return $first !== null ? $enabled[$first] : null;
    }

    protected static function isEnabled(string $sortId): bool
    {
        return CatalogControlSetting::isControlEnabled(CatalogControlSetting::TYPE_SORT, $sortId);
    }

    /**
     * Clear all registered sorts — called at the top of PluginManager::boot()
     * so each boot cycle starts from a genuinely clean registry state. Also
     * drops the shared enabled-map cache so stale settings never survive a
     * boot boundary (tests create a fresh Application — and a fresh DB — per
     * test method within the same PHP process).
     */
    public static function flush(): void
    {
        static::$sorts = [];
        CatalogControlSetting::resetCache();
    }
}
