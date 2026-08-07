<?php

namespace Tests\Feature\Recommendations;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Recommendations\Strategies\SameCategoryStrategy;
use Tests\TestCase;

class SameCategoryStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_vehicles_in_the_same_category(): void
    {
        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        Vehicle::factory()->create(['category' => 'economy', 'status' => 'available']);

        $results = (new SameCategoryStrategy)->getRecommendations($current, 4);

        $this->assertCount(1, $results);
        $this->assertSame('suv', $results->first()->category);
    }

    public function test_excludes_the_current_vehicle(): void
    {
        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);

        $results = (new SameCategoryStrategy)->getRecommendations($current, 4);

        $this->assertNotContains($current->id, $results->pluck('id')->all());
    }

    public function test_excludes_non_available_vehicles(): void
    {
        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        Vehicle::factory()->create(['category' => 'suv', 'status' => 'maintenance']);
        Vehicle::factory()->create(['category' => 'suv', 'status' => 'rented']);

        $results = (new SameCategoryStrategy)->getRecommendations($current, 4);

        $this->assertEmpty($results);
    }

    public function test_respects_the_limit(): void
    {
        $current = Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);
        Vehicle::factory()->count(3)->create(['category' => 'suv', 'status' => 'available']);

        $results = (new SameCategoryStrategy)->getRecommendations($current, 2);

        $this->assertCount(2, $results);
    }

    public function test_vehicle_without_category_returns_empty(): void
    {
        // category is a NOT NULL column — an empty string is the only way to
        // represent "no category", and the strategy's falsy guard handles it.
        $current = Vehicle::factory()->create(['category' => '', 'status' => 'available']);
        Vehicle::factory()->create(['category' => 'suv', 'status' => 'available']);

        $results = (new SameCategoryStrategy)->getRecommendations($current, 4);

        $this->assertEmpty($results);
    }
}
