<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Contracts\PaymentGateway;
use App\Models\Plugin;

class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGateway> keyed by gateway id */
    protected static array $gateways = [];

    /**
     * Maps gateway id → plugin slug so enabled() can cross-check the plugins DB table.
     * Gateway ids are short ('cod', 'stripe', 'cmi'); plugin slugs are 'payments-cod' etc.
     *
     * @var array<string, string>
     */
    protected static array $pluginSlugs = [];

    /**
     * @param  string  $pluginSlug  The slug as it appears in the plugins DB table (e.g. 'payments-cod').
     *                              Must be supplied so enabled() can filter correctly at runtime.
     */
    public static function register(PaymentGateway $gateway, string $pluginSlug): void
    {
        static::$gateways[$gateway->id()] = $gateway;
        static::$pluginSlugs[$gateway->id()] = $pluginSlug;
    }

    /**
     * Returns only gateways whose associated plugin is currently enabled in the DB.
     * Querying the DB on every checkout render is intentional: it lets an operator
     * disable a gateway via Filament and have it disappear on the next request
     * without needing a server restart.
     *
     * @return array<string, PaymentGateway>
     */
    public static function enabled(): array
    {
        $enabledSlugs = Plugin::where('is_enabled', true)
            ->pluck('slug')
            ->toArray();

        return array_filter(
            static::$gateways,
            fn ($gateway, $id) => in_array(static::$pluginSlugs[$id] ?? '', $enabledSlugs, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public static function get(string $id): ?PaymentGateway
    {
        return static::$gateways[$id] ?? null;
    }

    /**
     * Returns every gateway declared in config/plugins.php `gateways` manifest,
     * with an `isEnabled` flag. Disabled plugins never boot their ServiceProvider
     * so they never call register() — this manifest is the only way to know they
     * exist. Use this when the UI should show all payment options, with disabled
     * ones grayed-out rather than hidden (e.g. "CMI — coming soon").
     *
     * @return array<int, array{id: string, label: string, requiresRedirect: bool, isEnabled: bool, gateway: ?PaymentGateway}>
     */
    public static function all(): array
    {
        $manifest = (array) config('plugins.gateways', []);
        $enabledSlugs = Plugin::where('is_enabled', true)
            ->pluck('slug')
            ->toArray();

        $result = [];
        foreach ($manifest as $id => $meta) {
            $pluginSlug = (string) ($meta['plugin'] ?? '');
            $isEnabled = in_array($pluginSlug, $enabledSlugs, true);

            $result[] = [
                'id' => $id,
                'label' => (string) ($meta['label'] ?? $id),
                'requiresRedirect' => (bool) ($meta['requiresRedirect'] ?? false),
                'isEnabled' => $isEnabled,
                // Full gateway object only available when the plugin is enabled and booted.
                'gateway' => static::$gateways[$id] ?? null,
            ];
        }

        return $result;
    }

    /** For testing — resets both maps. */
    public static function flush(): void
    {
        static::$gateways = [];
        static::$pluginSlugs = [];
    }
}
