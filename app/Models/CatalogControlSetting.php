<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Per-control enable/disable setting for the storefront fleet listing: which
 * registered VehicleFilterRegistry filters and VehicleSortRegistry sorts are
 * active. One row per (control_type, control_id); absence of a row means the
 * control is enabled (the pre-admin-control default, so a fresh install
 * behaves exactly as before this feature existed).
 *
 * Consumed by three places:
 *  - the CatalogControlSettings admin page upserts rows on save;
 *  - VehicleFilterRegistry / VehicleSortRegistry consult isControlEnabled()
 *    when applying filters / resolving sorts;
 *  - VehicleController exposes only enabled controls to the storefront
 *    FilterBar via the registries' enabled() accessors.
 */
class CatalogControlSetting extends Model
{
    public const TYPE_FILTER = 'filter';

    public const TYPE_SORT = 'sort';

    protected $fillable = [
        'control_type',
        'control_id',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Cache of the "type:id" => enabled map, hydrated once per process/request
     * and cleared via resetCache() — called from both registries' flush() on
     * every boot (the same static-state-across-boots guard as the registries
     * themselves) and from the admin page's save() so a toggle is visible to
     * the very next storefront request.
     *
     * @var array<string, bool>|null
     */
    protected static ?array $enabledCache = null;

    /**
     * Whether the given registered control is currently enabled. Unknown or
     * un-configured controls are enabled by default.
     */
    public static function isControlEnabled(string $controlType, string $controlId): bool
    {
        return static::enabledMap()[$controlType.':'.$controlId] ?? true;
    }

    /**
     * A single batched query for every control's enabled state, keyed by
     * "type:id" => bool. One query regardless of how many filters/sorts are
     * registered — the storefront never queries per control (rule 8).
     *
     * @return array<string, bool>
     */
    public static function enabledMap(): array
    {
        if (static::$enabledCache !== null) {
            return static::$enabledCache;
        }

        try {
            static::$enabledCache = static::query()
                ->get(['control_type', 'control_id', 'is_enabled'])
                ->mapWithKeys(fn (self $setting) => [
                    $setting->control_type.':'.$setting->control_id => (bool) $setting->is_enabled,
                ])
                ->all();
        } catch (QueryException) {
            // The catalog_control_settings table doesn't exist yet (fresh
            // install, or a test that never loads this core migration) —
            // every control is enabled by default, the same graceful
            // degradation as PluginManager::enabled().
            static::$enabledCache = [];
        }

        return static::$enabledCache;
    }

    /**
     * Drop the cached enabled map so the next isControlEnabled()/enabled()
     * call re-reads the table.
     */
    public static function resetCache(): void
    {
        static::$enabledCache = null;
    }
}
