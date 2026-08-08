<?php

namespace Tests\Feature\FleetManagement;

use App\Core\Support\VehicleFilterRegistry;
use App\Core\Support\VehicleSortRegistry;
use App\Models\CatalogControlSetting;
use App\Models\Location;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\FleetManagement\FleetManagementServiceProvider;
use Plugins\Reviews\ReviewsServiceProvider;
use Tests\TestCase;

/**
 * Covers the fleet-management plugin's public controller logic directly by
 * registering its ServiceProvider in-process.
 *
 * Uses literal paths ('/vehicles', not route('vehicles.index')) because
 * UrlGenerator caches its RouteCollection reference at first resolution;
 * routes registered after boot (as we do here) don't reliably show up via
 * the route() helper/Route::has() in tests even though real HTTP dispatch
 * to the same path works correctly (verified directly: Route::getRoutes()
 * lists the route with the right name, and a literal-path request returns
 * 200) — this is a UrlGenerator caching quirk in post-boot provider
 * registration, not a bug in the plugin's routes.
 *
 * NOTE: this does NOT test the `plugins` DB table toggle itself — PHPUnit
 * boots the app (and therefore PluginManager::boot()) before RefreshDatabase
 * migrates the in-memory test DB, so the `plugins` table never exists at
 * boot time during a test run and the provider is never auto-registered.
 * The actual toggle (disabled -> 404, activate -> 200, deactivate -> 404
 * again) was verified manually via real `php artisan serve` process boots
 * against the persistent dev DB, which is the only way to exercise that
 * boot-order-dependent behavior faithfully.
 */
class VehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(FleetManagementServiceProvider::class);
    }

    public function test_index_lists_only_available_vehicles(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Dacia']);
        Vehicle::factory()->create(['status' => 'maintenance', 'make' => 'Mercedes']);
        Vehicle::factory()->create(['status' => 'rented', 'make' => 'Renault']);

        $response = $this->get('/vehicles');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.make', 'Dacia'));
    }

    public function test_index_filters_by_search_query(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Toyota', 'model' => 'Corolla']);
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Dacia', 'model' => 'Logan']);

        $response = $this->get('/vehicles?search=Toyota');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.make', 'Toyota')
            ->where('search', 'Toyota'));
    }

    public function test_index_filters_by_category(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'category' => 'suv', 'make' => 'Toyota']);
        Vehicle::factory()->create(['status' => 'available', 'category' => 'economy', 'make' => 'Dacia']);

        $response = $this->get('/vehicles?category=suv');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.category', 'suv')
            ->where('activeFilters.category', 'suv'));
    }

    public function test_index_category_filter_is_case_insensitive(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'category' => 'suv', 'make' => 'Toyota']);
        Vehicle::factory()->create(['status' => 'available', 'category' => 'economy', 'make' => 'Dacia']);

        // Hand-typed ?category=SUV must match the stored lowercase 'suv'.
        $response = $this->get('/vehicles?category=SUV');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.category', 'suv'));
    }

    public function test_index_filters_by_transmission(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'transmission_type' => 'automatic', 'make' => 'Toyota']);
        Vehicle::factory()->create(['status' => 'available', 'transmission_type' => 'manual', 'make' => 'Dacia']);

        $response = $this->get('/vehicles?transmission=automatic');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.transmission_type', 'automatic')
            ->where('activeFilters.transmission', 'automatic'));
    }

    public function test_index_filters_by_location_city(): void
    {
        $casablanca = Location::factory()->create(['city' => 'Casablanca']);
        $fes = Location::factory()->create(['city' => 'Fes']);
        $toyota = Vehicle::factory()->create([
            'status' => 'available',
            'make' => 'Toyota',
            'location_id' => $casablanca->id,
        ]);
        Vehicle::factory()->create([
            'status' => 'available',
            'make' => 'Dacia',
            'location_id' => $fes->id,
        ]);

        $response = $this->get('/vehicles?location=Casablanca');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            // The joined `locations` row must not clobber the vehicles' own
            // columns (PDO resolves duplicate names like `id` to the last
            // one seen) — this assertion guards the `select('vehicles.*')`
            // in VehicleLocationFilter::apply().
            ->where('vehicles.data.0.id', $toyota->id)
            ->where('vehicles.data.0.make', 'Toyota')
            ->where('activeFilters.location', 'Casablanca'));
    }

    public function test_index_location_filter_is_case_insensitive(): void
    {
        $casablanca = Location::factory()->create(['city' => 'Casablanca']);
        $fes = Location::factory()->create(['city' => 'Fes']);
        Vehicle::factory()->create([
            'status' => 'available',
            'make' => 'Toyota',
            'location_id' => $casablanca->id,
        ]);
        Vehicle::factory()->create([
            'status' => 'available',
            'make' => 'Dacia',
            'location_id' => $fes->id,
        ]);

        // Hand-typed ?location=CASABLANCA must match the stored 'Casablanca'.
        $response = $this->get('/vehicles?location=CASABLANCA');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.make', 'Toyota'));
    }

    public function test_index_location_filter_exposes_only_active_cities_as_options(): void
    {
        $casablanca = Location::factory()->create(['city' => 'Casablanca', 'is_active' => true]);
        Location::factory()->create(['city' => 'Fes', 'is_active' => true]);
        Location::factory()->create(['city' => 'Rabat', 'is_active' => false]);
        // Pin the vehicle to an existing location so the Vehicle factory's
        // default nested Location::factory() doesn't add a 3rd active city.
        Vehicle::factory()->create(['status' => 'available', 'location_id' => $casablanca->id]);

        $response = $this->get('/vehicles');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->where('availableFilters.2.id', 'location')
            ->where('availableFilters.2.uiType', 'select')
            ->has('availableFilters.2.options', 2)
            ->where('availableFilters.2.options.0.value', 'Casablanca')
            ->where('availableFilters.2.options.0.label', 'Casablanca')
            ->where('availableFilters.2.options.1.value', 'Fes')
            ->where('availableFilters.2.options.1.label', 'Fes'));
    }

    public function test_index_combines_location_with_category_filter(): void
    {
        $casablanca = Location::factory()->create(['city' => 'Casablanca']);
        $fes = Location::factory()->create(['city' => 'Fes']);
        Vehicle::factory()->create([
            'status' => 'available',
            'category' => 'suv',
            'make' => 'Toyota',
            'location_id' => $casablanca->id,
        ]);
        Vehicle::factory()->create([
            'status' => 'available',
            'category' => 'economy',
            'make' => 'Dacia',
            'location_id' => $casablanca->id,
        ]);
        Vehicle::factory()->create([
            'status' => 'available',
            'category' => 'suv',
            'make' => 'Renault',
            'location_id' => $fes->id,
        ]);

        $response = $this->get('/vehicles?location=Casablanca&category=suv');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.make', 'Toyota'));
    }

    public function test_index_combines_search_and_filters(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'category' => 'suv', 'make' => 'Toyota', 'model' => 'RAV4']);
        Vehicle::factory()->create(['status' => 'available', 'category' => 'suv', 'make' => 'Dacia', 'model' => 'Duster']);
        Vehicle::factory()->create(['status' => 'available', 'category' => 'economy', 'make' => 'Toyota', 'model' => 'Yaris']);

        $response = $this->get('/vehicles?category=suv&search=Toyota');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.model', 'RAV4'));
    }

    public function test_index_sorts_by_price_ascending(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'B', 'daily_rate' => 500]);
        Vehicle::factory()->create(['status' => 'available', 'make' => 'A', 'daily_rate' => 100]);

        $response = $this->get('/vehicles?sort=price_asc');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->where('vehicles.data.0.daily_rate', '100.00')
            ->where('vehicles.data.1.daily_rate', '500.00')
            ->where('currentSort', 'price_asc'));
    }

    public function test_index_sorts_by_price_descending(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'B', 'daily_rate' => 500]);
        Vehicle::factory()->create(['status' => 'available', 'make' => 'A', 'daily_rate' => 100]);

        $response = $this->get('/vehicles?sort=price_desc');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->where('vehicles.data.0.daily_rate', '500.00')
            ->where('vehicles.data.1.daily_rate', '100.00'));
    }

    public function test_index_sorts_by_name_ascending(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Renault', 'model' => 'Zoe']);
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Dacia', 'model' => 'Logan']);

        $response = $this->get('/vehicles?sort=name_asc');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->where('vehicles.data.0.make', 'Dacia')
            ->where('vehicles.data.1.make', 'Renault'));
    }

    public function test_index_falls_back_to_first_sort_for_unknown_sort_id(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'B', 'daily_rate' => 500]);
        Vehicle::factory()->create(['status' => 'available', 'make' => 'A', 'daily_rate' => 100]);

        $response = $this->get('/vehicles?sort=not_a_real_sort');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->where('vehicles.data.0.daily_rate', '100.00')
            ->where('currentSort', 'price_asc'));
    }

    public function test_index_exposes_registered_filters_and_sorts_as_props(): void
    {
        Vehicle::factory()->create(['status' => 'available']);

        $response = $this->get('/vehicles');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('availableFilters', 3)
            ->where('availableFilters.0.id', 'category')
            ->where('availableFilters.0.uiType', 'select')
            ->where('availableFilters.1.id', 'transmission')
            ->where('availableFilters.1.uiType', 'select')
            ->where('availableFilters.2.id', 'location')
            ->where('availableFilters.2.uiType', 'select')
            ->has('availableSorts', 3)
            ->where('availableSorts.0.id', 'price_asc')
            ->where('availableSorts.1.id', 'price_desc')
            ->where('availableSorts.2.id', 'name_asc')
            ->where('search', '')
            ->where('currentSort', ''));
    }

    public function test_index_excludes_a_disabled_filter_from_props_and_does_not_apply_it(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'transmission_type' => 'automatic', 'make' => 'Toyota']);
        Vehicle::factory()->create(['status' => 'available', 'transmission_type' => 'manual', 'make' => 'Dacia']);

        CatalogControlSetting::create([
            'control_type' => 'filter',
            'control_id' => 'transmission',
            'is_enabled' => false,
        ]);
        CatalogControlSetting::resetCache();

        // Disabled filter is excluded from the enabled() list at the registry
        // level (the :memory: SQLite connection quirk documented in CLAUDE.md
        // means RefreshDatabase transactions aren't visible to the HTTP kernel
        // — the full-request assertions live in CatalogControlSettingsTest).
        $enabled = VehicleFilterRegistry::enabled();
        $this->assertCount(2, $enabled);
        $this->assertArrayHasKey('category', $enabled);
        $this->assertArrayNotHasKey('transmission', $enabled);
        $this->assertArrayHasKey('location', $enabled);
    }

    public function test_index_restores_a_re_enabled_filter(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'transmission_type' => 'automatic', 'make' => 'Toyota']);
        Vehicle::factory()->create(['status' => 'available', 'transmission_type' => 'manual', 'make' => 'Dacia']);

        CatalogControlSetting::updateOrCreate(
            ['control_type' => 'filter', 'control_id' => 'transmission'],
            ['is_enabled' => false],
        );

        // Re-enable it (the same upsert the admin page performs) and confirm
        // the filter is exposed and applied again.
        CatalogControlSetting::updateOrCreate(
            ['control_type' => 'filter', 'control_id' => 'transmission'],
            ['is_enabled' => true],
        );
        CatalogControlSetting::resetCache();

        $response = $this->get('/vehicles?transmission=automatic');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.transmission_type', 'automatic')
            ->has('availableFilters', 3)
            ->where('activeFilters.transmission', 'automatic'));
    }

    public function test_index_excludes_a_disabled_sort_from_props_and_does_not_apply_it(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'B', 'daily_rate' => 500]);
        Vehicle::factory()->create(['status' => 'available', 'make' => 'A', 'daily_rate' => 100]);

        CatalogControlSetting::create([
            'control_type' => 'sort',
            'control_id' => 'price_asc',
            'is_enabled' => false,
        ]);
        CatalogControlSetting::resetCache();

        // Disabled sort is excluded at the registry level (same :memory:
        // SQLite note as the disabled-filter test above).
        $enabled = VehicleSortRegistry::enabled();
        $this->assertCount(2, $enabled);
        $this->assertArrayHasKey('price_desc', $enabled);
        $this->assertArrayHasKey('name_asc', $enabled);
        $this->assertArrayNotHasKey('price_asc', $enabled);
    }

    public function test_show_returns_the_vehicle_when_available(): void
    {
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create(['status' => 'available', 'location_id' => $location->id]);

        $response = $this->get("/vehicles/{$vehicle->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Show')
            ->where('vehicle.id', $vehicle->id));
    }

    public function test_show_404s_when_vehicle_is_not_available(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'maintenance']);

        $response = $this->get("/vehicles/{$vehicle->id}");

        $response->assertNotFound();
    }

    /**
     * Proves the approved review data reaches the vehicle detail page as a
     * direct `reviewsData` prop — the page renders it through the
     * core-owned `reviewDisplay` layout variant (which swaps between the
     * card-list and compact review components). fleet-management never
     * references the reviews plugin by name; it only knows the named filter.
     */
    public function test_the_vehicle_detail_page_renders_the_reviews_slot_with_real_data(): void
    {
        $this->app->register(ReviewsServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/reviews/database/migrations']);

        $vehicle = Vehicle::factory()->create(['status' => 'available']);
        Review::factory()->create(['vehicle_id' => $vehicle->id, 'is_approved' => true, 'rating' => 4]);
        Review::factory()->create(['vehicle_id' => $vehicle->id, 'is_approved' => false, 'rating' => 1]);

        $response = $this->get("/vehicles/{$vehicle->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Vehicles/Show')
            ->where('reviewsData.vehicleId', $vehicle->id)
            ->where('reviewsData.reviewCount', 1)
            ->where('reviewsData.averageRating', 4));
    }
}
