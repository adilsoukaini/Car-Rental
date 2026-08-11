<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Models\Plugin;
use Illuminate\Database\QueryException;

class PluginManager
{
    /**
     * Return slugs of all currently-enabled plugins from the DB.
     *
     * Catches QueryException only — the one case where we legitimately want
     * to proceed with no plugins is when the `plugins` table doesn't exist
     * yet (fresh install, before migrations run). Any other exception (bad
     * config, logic error) should still propagate normally.
     */
    public static function enabled(): array
    {
        try {
            return Plugin::where('is_enabled', true)->pluck('slug')->toArray();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * Register Service Providers for all enabled plugins.
     *
     * Called from AppServiceProvider::boot() (not register() — Eloquent is
     * unavailable during register()). Resolves each enabled slug against
     * config/plugins.php and registers its provider into the container,
     * replicating the WordPress "activate plugin" lifecycle.
     *
     * A slug present in the DB but missing from config/plugins.php is
     * silently skipped — this happens when a plugin is removed from the
     * codebase but its DB row hasn't been cleaned up yet.
     *
     * Flushes FilterRegistry/SlotRegistry first: their static state survives
     * across Application boots within the same PHP process (every test
     * method creates a fresh Application but reuses the same process; a
     * persistent-worker deployment model like Octane would too), so without
     * this, every re-run of boot() silently accumulates duplicate pipe/slot
     * entries on top of whatever a previous boot already registered.
     */
    public static function boot(): void
    {
        FilterRegistry::flush();
        SlotRegistry::flush();
        LayoutVariantRegistry::flush();
        VehicleFilterRegistry::flush();
        VehicleSortRegistry::flush();
        VehicleResourceExtension::flush();

        foreach (static::enabled() as $slug) {
            $providerClass = config("plugins.registry.{$slug}");

            if (! $providerClass) {
                \Illuminate\Support\Facades\Log::warning("PluginManager: no provider for slug [{$slug}]");
                continue;
            }
            if (! class_exists($providerClass)) {
                \Illuminate\Support\Facades\Log::warning("PluginManager: class [{$providerClass}] not found for slug [{$slug}]");
                continue;
            }
            \Illuminate\Support\Facades\Log::info("PluginManager: registering [{$providerClass}] for slug [{$slug}]");
            app()->register($providerClass);
        }
    }

    /**
     * Activate a plugin by slug (set is_enabled = true).
     * Creates the DB row if it doesn't exist yet.
     */
    public static function activate(string $slug): void
    {
        Plugin::updateOrCreate(['slug' => $slug], ['is_enabled' => true]);
    }

    /**
     * Deactivate a plugin by slug (set is_enabled = false).
     * Data is preserved — this is not an uninstall.
     */
    public static function deactivate(string $slug): void
    {
        Plugin::where('slug', $slug)->update(['is_enabled' => false]);
    }
}
