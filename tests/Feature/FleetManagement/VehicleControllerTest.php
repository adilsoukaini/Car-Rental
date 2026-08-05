<?php

namespace Tests\Feature\FleetManagement;

use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\FleetManagement\FleetManagementServiceProvider;
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
}
