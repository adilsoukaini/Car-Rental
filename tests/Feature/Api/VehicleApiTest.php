<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JSON fleet/location/search API endpoints for the mobile app. The query
 * building under test is the same VehicleCatalogService the storefront uses,
 * so these tests double as a guard that the web + API listing behavior stays
 * in lockstep.
 */
class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Vehicle::factory()->create() triggers Scout's Searchable observer,
        // which would POST to Meilisearch (not running in the automated suite)
        // and fail with a connection error. Pin the offline `database` driver
        // so tests are self-sufficient regardless of test ordering (the
        // pre-existing fleet tests only pass in the full suite by the same
        // ordering coincidence). The database driver's update() is a no-op —
        // no network involved.
        config(['scout.driver' => 'database']);
    }

    public function test_vehicles_index_returns_paginated_json_of_available_vehicles(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Dacia']);
        Vehicle::factory()->create(['status' => 'maintenance', 'make' => 'Mercedes']);
        Vehicle::factory()->create(['status' => 'rented', 'make' => 'Renault']);

        $response = $this->getJson('/api/vehicles');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.make', 'Dacia');
    }

    public function test_vehicles_index_respects_per_page(): void
    {
        Vehicle::factory()->count(3)->create(['status' => 'available']);

        $response = $this->getJson('/api/vehicles?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('per_page', 2);
    }

    public function test_vehicles_index_filters_and_searches(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'category' => 'suv', 'make' => 'Toyota', 'model' => 'RAV4']);
        Vehicle::factory()->create(['status' => 'available', 'category' => 'economy', 'make' => 'Dacia', 'model' => 'Logan']);

        $response = $this->getJson('/api/vehicles?category=suv&search=Toyota');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.make', 'Toyota');
    }

    public function test_vehicle_show_returns_the_detail_json_shape(): void
    {
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create(['status' => 'available', 'location_id' => $location->id]);

        $response = $this->getJson("/api/vehicles/{$vehicle->id}");

        $response->assertOk();
        $response->assertJsonPath('vehicle.id', $vehicle->id);
        $response->assertJsonPath('vehicle.location.id', $location->id);
        $response->assertJsonStructure([
            'vehicle',
            'galleryImages',
            'reviewsData' => ['vehicleId', 'averageRating', 'reviewCount', 'reviews'],
            'attributes',
            'recommendations',
        ]);
    }

    public function test_vehicle_show_404s_for_unavailable_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'maintenance']);

        $this->getJson("/api/vehicles/{$vehicle->id}")->assertNotFound();
    }

    public function test_search_suggestions_returns_a_json_array(): void
    {
        Vehicle::factory()->create(['status' => 'available', 'make' => 'Toyota', 'model' => 'Corolla']);

        $response = $this->getJson('/api/search/suggestions?q=toyo');

        $response->assertOk();
        $response->assertJsonIsArray();
        $response->assertJsonPath('0.make', 'Toyota');
    }

    public function test_locations_index_returns_only_active_locations(): void
    {
        Location::factory()->create(['city' => 'Casablanca', 'is_active' => true]);
        Location::factory()->create(['city' => 'Fes', 'is_active' => false]);

        $response = $this->getJson('/api/locations');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.city', 'Casablanca');
        $response->assertJsonStructure([['id', 'name', 'address_line', 'city', 'country']]);
    }
}
