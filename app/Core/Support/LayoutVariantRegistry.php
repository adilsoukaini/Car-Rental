<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Models\LayoutSetting;

/**
 * Lets a page region (e.g. the fleet-listing layout, the vehicle-detail
 * layout, the checkout layout) offer more than one real visual arrangement
 * of the same underlying data, swappable by an admin without a code
 * change. Ported from the e-commerce project's real, proven mechanism —
 * deliberately NOT built earlier in this project (see
 * docs/event-registry.md's prior note) because no second real variant
 * existed for anything yet. That's no longer true: the Stitch design
 * source for this project's own storefront contains genuinely different
 * layout variants for the fleet listing, vehicle detail, and checkout
 * screens — a real trigger, not a hypothetical one.
 */
class LayoutVariantRegistry
{
    /** @var array<string, list<array{variantId:string, label:string, componentName:string, pluginSlug:string}>> */
    protected static array $variants = [];

    public static function register(
        string $slotName,
        string $variantId,
        string $label,
        string $componentName,
        string $pluginSlug = 'core',
    ): void {
        static::$variants[$slotName][] = compact('variantId', 'label', 'componentName', 'pluginSlug');
    }

    /** Every registered option for a region — used to populate the admin picker. */
    public static function availableFor(string $slotName): array
    {
        return static::$variants[$slotName] ?? [];
    }

    /** All registered region names — used by HandleInertiaRequests to share all regions dynamically. */
    public static function allRegisteredSlots(): array
    {
        return array_keys(static::$variants);
    }

    /**
     * Which component name is currently active for this region.
     * Falls back to the first registered variant when no DB row exists yet.
     */
    public static function activeComponentFor(string $slotName): string
    {
        $activeId = LayoutSetting::where('slot_name', $slotName)->value('active_variant_id');
        $options = static::$variants[$slotName] ?? [];

        $chosen = collect($options)->firstWhere('variantId', $activeId) ?? $options[0] ?? null;

        if ($chosen === null) {
            throw new \RuntimeException("No layout variant registered for slot '{$slotName}'.");
        }

        return $chosen['componentName'];
    }

    /**
     * Clear all registered variants. Static state survives across
     * Application boots within the same PHP process — see
     * PluginManager::boot()'s docblock and CLAUDE.md's registry-flush
     * kernel fix for why this matters.
     */
    public static function flush(): void
    {
        static::$variants = [];
    }
}
