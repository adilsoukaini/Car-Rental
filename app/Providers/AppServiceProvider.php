<?php

namespace App\Providers;

use App\Core\Events\BookingConfirmed;
use App\Core\Listeners\SendBookingConfirmationEmail;
use App\Core\Support\LayoutVariantRegistry;
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

        // vehicleCard layout variants — the storefront's vehicle-card region
        // (rendered via LayoutSlot name="vehicleCard" on the homepage, and
        // the future fleet listing). Registered in core (not the
        // fleet-management plugin) because the vehicle-card components and
        // the primary consumer (Home) are both core-owned — core must not
        // depend on a plugin being enabled for its own homepage to work.
        // Matches the component names in resources/js/layoutComponentRegistry.tsx.
        LayoutVariantRegistry::register('vehicleCard', 'vertical', 'Vertical', 'Layout/VehicleCard/Vertical');
        LayoutVariantRegistry::register('vehicleCard', 'horizontal-split', 'Horizontal Split', 'Layout/VehicleCard/HorizontalSplit');

        // fleetLayout page-layout variants — the storefront's fleet-listing
        // page (resources/js/Pages/Vehicles/Index.tsx) renders the search/
        // filter controls inline above the grid (default) or in a sticky
        // sidebar beside it. Unlike vehicleCard these aren't mapped to
        // separate React components in layoutComponentRegistry.tsx (no
        // LayoutSlot is used for the page itself): Index.tsx reads the
        // active component name directly from the shared
        // activeLayoutVariants prop and switches its render. The
        // componentName strings are the exact variant keys that page checks.
        // Registered in core (not the fleet-management plugin) because the
        // page component being switched is core-owned — the same precedent
        // as vehicleCard above.
        LayoutVariantRegistry::register('fleetLayout', 'default', 'Inline Search', 'fleet-layout-default', 'fleet-management');
        LayoutVariantRegistry::register('fleetLayout', 'sidebar', 'Sidebar Search', 'fleet-layout-sidebar', 'fleet-management');

        // reviewDisplay layout variants — how reviews render on the vehicle
        // detail page (resources/js/Pages/Vehicles/Show.tsx). Previously
        // reviews rendered via a single SlotRegistry entry
        // (vehicle.detailWidgets -> Widgets/VehicleReviews); the display is
        // now a LayoutVariant so an admin can switch between a full card list
        // (default) and a compact inline list. Registered in core (not the
        // reviews plugin) because the components are core-owned
        // (resources/js/Widgets/) — the same precedent as vehicleCard /
        // fleetLayout above. The 'reviews' plugin slug is metadata for the
        // admin picker, not a class reference (Hard Rule 1 safe). Matches the
        // component names in resources/js/layoutComponentRegistry.tsx.
        LayoutVariantRegistry::register('reviewDisplay', 'card-list', 'Card List', 'Widgets/VehicleReviewsCardList', 'reviews');
        LayoutVariantRegistry::register('reviewDisplay', 'compact', 'Compact', 'Widgets/VehicleReviewsCompact', 'reviews');

        // checkoutStyle page-layout variants — how the booking checkout page
        // (resources/js/Pages/Bookings/Checkout.tsx) arranges the form and
        // the price summary. 'sidebar-flow' is the existing 2-column design
        // (form left, sticky summary right, default); 'vertical-stack' is a
        // single centered column with the summary card stacked below the
        // form. Like fleetLayout these aren't mapped to separate React
        // components in layoutComponentRegistry.tsx (no LayoutSlot is used
        // for the page itself): Checkout.tsx reads the active component name
        // directly from the shared activeLayoutVariants prop and switches
        // its render. The componentName strings are the exact keys that page
        // checks.
        LayoutVariantRegistry::register('checkoutStyle', 'sidebar-flow', 'Sidebar Flow', 'checkout-sidebar', 'booking-engine');
        LayoutVariantRegistry::register('checkoutStyle', 'vertical-stack', 'Vertical Stack', 'checkout-vertical', 'booking-engine');

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
