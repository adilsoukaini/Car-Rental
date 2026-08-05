<?php

namespace App\Providers;

use App\Core\Events\BookingConfirmed;
use App\Core\Listeners\SendBookingConfirmationEmail;
use App\Core\Support\PluginManager;
use App\Core\Support\SlotRegistry;
use App\Core\Support\ThemeSchemaRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Must run in boot(), not register() — Eloquent's DB resolver isn't
        // available during register() (DatabaseServiceProvider::boot() sets it).
        // Plugin providers registered here get both register() and boot() called
        // immediately by Laravel because the app is already in boot phase.
        PluginManager::boot();

        Event::listen(BookingConfirmed::class, SendBookingConfirmationEmail::class);

        SlotRegistry::register('account.dashboardWidgets', 'Widgets/BookingHistory');

        // Plugins may call ThemeSchemaRegistry::registerField() from their own
        // boot() to add their own token fields — matches the Semantic interface
        // in resources/theme/semantic.ts. Keyed by dot-path, not appended to a
        // list, so re-registering the same path on every boot is naturally
        // idempotent — no flush() needed, unlike FilterRegistry/SlotRegistry.
        ThemeSchemaRegistry::registerField('color.primary', 'color');
        ThemeSchemaRegistry::registerField('color.primaryHover', 'color');
        ThemeSchemaRegistry::registerField('color.onPrimary', 'color');
        ThemeSchemaRegistry::registerField('color.secondary', 'color');
        ThemeSchemaRegistry::registerField('color.onSecondary', 'color');
        ThemeSchemaRegistry::registerField('color.background', 'color');
        ThemeSchemaRegistry::registerField('color.surface', 'color');
        ThemeSchemaRegistry::registerField('color.surfaceRaised', 'color');
        ThemeSchemaRegistry::registerField('color.text', 'color');
        ThemeSchemaRegistry::registerField('color.textMuted', 'color');
        ThemeSchemaRegistry::registerField('color.border', 'color');
        ThemeSchemaRegistry::registerField('color.success', 'color');
        ThemeSchemaRegistry::registerField('color.danger', 'color');
        ThemeSchemaRegistry::registerField('color.warning', 'color');
        ThemeSchemaRegistry::registerField('color.focusRing', 'color');
        ThemeSchemaRegistry::registerField('color.onPhoto', 'color', required: false);
        ThemeSchemaRegistry::registerField('color.photoScrim', 'color', required: false);
        ThemeSchemaRegistry::registerField('font.display', 'string');
        ThemeSchemaRegistry::registerField('font.body', 'string');
        ThemeSchemaRegistry::registerField('font.mono', 'string');
        ThemeSchemaRegistry::registerField('radius.interactive', 'string');
        ThemeSchemaRegistry::registerField('radius.container', 'string');
        ThemeSchemaRegistry::registerField('radius.pill', 'string');
        ThemeSchemaRegistry::registerField('shadow.resting', 'string');
        ThemeSchemaRegistry::registerField('shadow.raised', 'string');
        ThemeSchemaRegistry::registerField('shadow.overlay', 'string');

        Vite::prefetch(concurrency: 3);
    }
}
