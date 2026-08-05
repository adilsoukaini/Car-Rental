<?php

declare(strict_types=1);

namespace App\Core\Support;

class SlotRegistry
{
    protected static array $slots = [];

    /**
     * Register a React component name into a named slot.
     *
     * $componentName must match a key in resources/js/pluginComponentRegistry.tsx.
     * Plugins call this from their ServiceProvider::boot().
     */
    public static function register(string $slot, string $componentName, int $priority = 10): void
    {
        static::$slots[$slot][] = ['component' => $componentName, 'priority' => $priority];
        usort(static::$slots[$slot], fn ($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Return a serialisable array of {component, props} objects for an Inertia prop.
     *
     * The React <SlotOutlet slot={slots} /> component resolves component names
     * against pluginComponentRegistry.tsx and renders them in priority order.
     */
    public static function render(string $slot, array $props = []): array
    {
        return array_map(
            fn ($s) => ['component' => $s['component'], 'props' => $props],
            static::$slots[$slot] ?? []
        );
    }

    /**
     * Return all registered slot names — useful for debugging and admin tooling.
     */
    public static function registeredSlots(): array
    {
        return array_keys(static::$slots);
    }

    /**
     * Clear all registered slots.
     *
     * Static registry state survives across Application boots within the
     * same PHP process (every test method creates a fresh Application but
     * reuses the same process, and a persistent-worker deployment model
     * like Octane would too) — without this, every re-run of boot() would
     * silently accumulate duplicate entries on top of whatever was already
     * registered. Called at the top of PluginManager::boot() specifically
     * so each boot cycle starts from a genuinely clean slate.
     */
    public static function flush(): void
    {
        static::$slots = [];
    }
}
