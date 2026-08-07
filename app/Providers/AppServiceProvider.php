<?php

namespace App\Providers;

use App\Core\Events\BookingCancelled;
use App\Core\Events\BookingConfirmed;
use App\Core\Events\VehicleCheckedOut;
use App\Core\Events\VehicleReturned;
use App\Core\Filters\VehicleCategoryFilter;
use App\Core\Filters\VehicleTransmissionFilter;
use App\Core\Listeners\SendBookingCancelledEmail;
use App\Core\Listeners\SendBookingCheckedOutEmail;
use App\Core\Listeners\SendBookingConfirmationEmail;
use App\Core\Listeners\SendBookingReturnedEmail;
use App\Core\Sorts\VehicleNameAscending;
use App\Core\Sorts\VehiclePriceAscending;
use App\Core\Sorts\VehiclePriceDescending;
use App\Core\Support\LayoutVariantRegistry;
use App\Core\Support\PluginManager;
use App\Core\Support\SlotRegistry;
use App\Core\Support\ThemeSchemaRegistry;
use App\Core\Support\VehicleFilterRegistry;
use App\Core\Support\VehicleSortRegistry;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Scout;
use Meilisearch\Client as MeilisearchClient;

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
        // The Meilisearch\Client is normally bound by Scout's own service
        // provider, which only passes host/key/clientAgents — it never reads
        // the `timeout` config key. Override that binding here so
        // `scout.meilisearch.timeout` actually reaches the underlying HTTP
        // client: a PSR-18 Guzzle client with the timeout applied. Default
        // Guzzle behavior is NO timeout — a hung Meilisearch would block the
        // request indefinitely; with a timeout it fails fast and
        // SearchController's catch(\Throwable) falls back to the database.
        // Scout's own clientAgents string is preserved for parity.
        $this->app->singleton(MeilisearchClient::class, function ($app) {
            $config = $app['config']->get('scout.meilisearch');

            return new MeilisearchClient(
                $config['host'],
                $config['key'],
                new GuzzleClient(['timeout' => $config['timeout'] ?? 5]),
                clientAgents: [sprintf('Meilisearch Laravel Scout (v%s)', Scout::VERSION)],
            );
        });

        // Must run in boot(), not register() — Eloquent's DB resolver isn't
        // available during register() (DatabaseServiceProvider::boot() sets it).
        // Plugin providers registered here get both register() and boot() called
        // immediately by Laravel because the app is already in boot phase.
        PluginManager::boot();

        Event::listen(BookingConfirmed::class, SendBookingConfirmationEmail::class);
        Event::listen(VehicleCheckedOut::class, SendBookingCheckedOutEmail::class);
        Event::listen(VehicleReturned::class, SendBookingReturnedEmail::class);
        Event::listen(BookingCancelled::class, SendBookingCancelledEmail::class);

        SlotRegistry::register('account.dashboardWidgets', 'Widgets/BookingHistory');

        // Fleet-listing filters — the registry-based filter/sort layer the
        // storefront's /vehicles page consumes. Registered in core (not the
        // fleet-management plugin) because the Vehicle model, the registries,
        // and the VehicleController's orchestration are all core-owned — a
        // future plugin adds its own filter by registering a provider from
        // its own ServiceProvider, never by editing this file.
        VehicleFilterRegistry::register(new VehicleCategoryFilter);
        VehicleFilterRegistry::register(new VehicleTransmissionFilter);

        // Fleet-listing sorts — same rationale as the filters above.
        VehicleSortRegistry::register(new VehiclePriceAscending);
        VehicleSortRegistry::register(new VehiclePriceDescending);
        VehicleSortRegistry::register(new VehicleNameAscending);

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

        // vehicle-gallery layout variants — how the gallery renders on the
        // vehicle detail page (resources/js/Pages/Vehicles/Show.tsx). The
        // gallery region renders via LayoutSlot name="vehicle-gallery"; an
        // admin can switch between the default single-hero (big image + dot
        // indicators) and a carousel (prev/next arrows + thumbnail strip).
        // Registered in core (not the vehicle-media plugin) because the
        // gallery components and the primary consumer (Show.tsx) are both
        // core-owned — the same precedent as vehicleCard / reviewDisplay
        // above. The 'vehicle-media' plugin slug is metadata for the admin
        // picker, not a class reference (Hard Rule 1 safe). Matches the
        // component names in resources/js/layoutComponentRegistry.tsx.
        LayoutVariantRegistry::register('vehicle-gallery', 'single-hero', 'Single Hero', 'Components/VehicleGallery', 'vehicle-media');
        LayoutVariantRegistry::register('vehicle-gallery', 'carousel', 'Carousel', 'Components/VehicleGalleryCarousel', 'vehicle-media');

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
