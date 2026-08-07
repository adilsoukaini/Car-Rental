<?php

namespace Tests\Feature\Recommendations;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Recommendations\Strategies\SimilarPriceStrategy;
use Tests\TestCase;

class SimilarPriceStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_vehicles_within_thirty_percent_of_the_rate(): void
    {
        $current = Vehicle::factory()->create(['daily_rate' => 1000, 'status' => 'available']);

        // Within ±30% of 1000 (i.e. 700–1300).
        Vehicle::factory()->create(['daily_rate' => 700, 'status' => 'available']);
        Vehicle::factory()->create(['daily_rate' => 1300, 'status' => 'available']);
        Vehicle::factory()->create(['daily_rate' => 1000, 'status' => 'available']);

        $results = (new SimilarPriceStrategy)->getRecommendations($current, 4);

        $this->assertCount(3, $results);
        foreach ($results as $v) {
            $rate = (float) $v->daily_rate;
            $this->assertGreaterThanOrEqual(700, $rate);
            $this->assertLessThanOrEqual(1300, $rate);
        }
    }

    public function test_excludes_vehicles_outside_the_price_band(): void
    {
        $current = Vehicle::factory()->create(['daily_rate' => 1000, 'status' => 'available']);
        Vehicle::factory()->create(['daily_rate' => 699, 'status' => 'available']);
        Vehicle::factory()->create(['daily_rate' => 1301, 'status' => 'available']);

        $results = (new SimilarPriceStrategy)->getRecommendations($current, 4);

        $this->assertEmpty($results);
    }

    public function test_excludes_the_current_vehicle(): void
    {
        $current = Vehicle::factory()->create(['daily_rate' => 1000, 'status' => 'available']);

        $results = (new SimilarPriceStrategy)->getRecommendations($current, 4);

        $this->assertNotContains($current->id, $results->pluck('id')->all());
    }

    public function test_excludes_non_available_vehicles(): void
    {
        $current = Vehicle::factory()->create(['daily_rate' => 1000, 'status' => 'available']);
        Vehicle::factory()->create(['daily_rate' => 900, 'status' => 'maintenance']);
        Vehicle::factory()->create(['daily_rate' => 1100, 'status' => 'rented']);

        $results = (new SimilarPriceStrategy)->getRecommendations($current, 4);

        $this->assertEmpty($results);
    }

    public function test_respects_the_limit(): void
    {
        $current = Vehicle::factory()->create(['daily_rate' => 1000, 'status' => 'available']);
        Vehicle::factory()->count(3)->create(['daily_rate' => 1000, 'status' => 'available']);

        $results = (new SimilarPriceStrategy)->getRecommendations($current, 2);

        $this->assertCount(2, $results);
    }
}
