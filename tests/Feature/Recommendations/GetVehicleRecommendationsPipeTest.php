<?php

namespace Tests\Feature\Recommendations;

use App\Core\Support\FilterRegistry;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Recommendations\RecommendationsServiceProvider;
use Plugins\VehicleMedia\Models\VehicleImage;
use Plugins\VehicleMedia\VehicleMediaServiceProvider;
use Tests\TestCase;

/**
 * Exercises the vehicle.recommendations filter the way VehicleController::show()
 * invokes it — via FilterRegistry::applyWithContext() with the current Vehicle
 * bound into the container. The vehicle-media tests are deliberately LAST in
 * this class (PHPUnit runs methods in declaration order) and tearDown() resets
 * the dynamically-registered primaryImage relation afterward, so the
 * "no vehicle-media" assertions above can never observe leaked static state
 * (static $relationResolvers survives across the PHPUnit process — same class
 * of concern as the FilterRegistry accumulation bug, but not flushed by
 * PluginManager::boot()).
 */
class GetVehicleRecommendationsPipeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(RecommendationsServiceProvider::class);
    }

    /** @return array<mixed> */
    private function fetch(Vehicle $vehicle): array
    {
        return FilterRegistry::applyWithContext(
            'vehicle.recommendations',
            [],
            [Vehicle::class => $vehicle],
        );
    }

    public function test_returns_same_category_recommendations_as_mapped_cards(): void
    {
        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        Vehicle::factory()->create(['category' => 'economy', 'status' => 'available']);

        $result = $this->fetch($current);

        $this->assertCount(1, $result);
        $this->assertSame('suv', $result[0]['category']);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('make', $result[0]);
        $this->assertArrayHasKey('model', $result[0]);
        $this->assertArrayHasKey('daily_rate', $result[0]);
        $this->assertArrayHasKey('imageUrl', $result[0]);
    }

    public function test_returns_empty_when_no_similar_vehicles(): void
    {
        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);

        $result = $this->fetch($current);

        $this->assertSame([], $result);
    }

    public function test_uses_similar_price_strategy_when_configured(): void
    {
        config(['recommendations.active_strategy' => 'similar_price']);

        $current = Vehicle::factory()->create(['category' => 'suv', 'daily_rate' => 1000, 'status' => 'available']);
        // Within ±30% of 1000 regardless of category.
        Vehicle::factory()->create(['category' => 'suv', 'daily_rate' => 900, 'status' => 'available']);
        Vehicle::factory()->create(['category' => 'economy', 'daily_rate' => 1000, 'status' => 'available']);
        // Outside the band — must be excluded even though same category.
        Vehicle::factory()->create(['category' => 'suv', 'daily_rate' => 2000, 'status' => 'available']);

        $result = $this->fetch($current);

        $this->assertCount(2, $result);
    }

    public function test_respects_max_results_config(): void
    {
        config(['recommendations.max_results' => 1]);

        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        Vehicle::factory()->count(3)->create(['category' => 'suv', 'status' => 'available']);

        $result = $this->fetch($current);

        $this->assertCount(1, $result);
    }

    // === vehicle-media-dependent tests (must stay after the tests above) ===

    public function test_primary_image_url_is_null_without_vehicle_media(): void
    {
        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);

        $result = $this->fetch($current);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['imageUrl']);
    }

    public function test_primary_image_url_is_populated_when_vehicle_media_is_active(): void
    {
        $this->app->register(VehicleMediaServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/vehicle-media/database/migrations']);

        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        $similar = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        VehicleImage::create([
            'vehicle_id' => $similar->id,
            'path' => 'https://example.com/suv.jpg',
            'alt_text' => 'SUV',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $result = $this->fetch($current);

        $this->assertCount(1, $result);
        $this->assertSame('https://example.com/suv.jpg', $result[0]['imageUrl']);
    }

    protected function tearDown(): void
    {
        // Reset the dynamically-registered primaryImage relation so it cannot
        // leak into other test classes (static $relationResolvers survives the
        // PHPUnit process — not flushed by PluginManager::boot()).
        $prop = new \ReflectionProperty(Vehicle::class, 'relationResolvers');
        $prop->setAccessible(true);
        $resolvers = $prop->getValue();
        unset($resolvers[Vehicle::class]['primaryImage']);
        $prop->setValue(null, $resolvers);

        parent::tearDown();
    }
}
