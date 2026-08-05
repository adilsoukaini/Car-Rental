<?php

namespace Tests\Feature\Reviews;

use App\Core\Support\FilterRegistry;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Reviews\ReviewsServiceProvider;
use Tests\TestCase;

class GetVehicleReviewsPipeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(ReviewsServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/reviews/database/migrations']);
    }

    /** @return array<string, mixed> */
    private function fetch(Vehicle $vehicle): array
    {
        return FilterRegistry::applyWithContext(
            'vehicle.reviews',
            ['vehicleId' => $vehicle->id, 'averageRating' => 0.0, 'reviewCount' => 0, 'reviews' => []],
            [Vehicle::class => $vehicle],
        );
    }

    public function test_only_approved_reviews_are_returned(): void
    {
        $vehicle = Vehicle::factory()->create();
        Review::factory()->create(['vehicle_id' => $vehicle->id, 'is_approved' => true, 'rating' => 5]);
        Review::factory()->create(['vehicle_id' => $vehicle->id, 'is_approved' => false, 'rating' => 1]);

        $result = $this->fetch($vehicle);

        $this->assertSame(1, $result['reviewCount']);
        $this->assertSame(5.0, $result['averageRating']);
    }

    public function test_average_rating_is_computed_correctly_across_approved_reviews(): void
    {
        $vehicle = Vehicle::factory()->create();
        Review::factory()->create(['vehicle_id' => $vehicle->id, 'is_approved' => true, 'rating' => 5]);
        Review::factory()->create(['vehicle_id' => $vehicle->id, 'is_approved' => true, 'rating' => 3]);

        $result = $this->fetch($vehicle);

        $this->assertSame(2, $result['reviewCount']);
        $this->assertSame(4.0, $result['averageRating']);
    }

    public function test_reviews_from_a_different_vehicle_are_excluded(): void
    {
        $vehicle = Vehicle::factory()->create();
        $otherVehicle = Vehicle::factory()->create();
        Review::factory()->create(['vehicle_id' => $otherVehicle->id, 'is_approved' => true, 'rating' => 5]);

        $result = $this->fetch($vehicle);

        $this->assertSame(0, $result['reviewCount']);
    }

    public function test_a_vehicle_with_no_reviews_returns_zeroed_defaults(): void
    {
        $vehicle = Vehicle::factory()->create();

        $result = $this->fetch($vehicle);

        $this->assertSame(0, $result['reviewCount']);
        $this->assertSame(0.0, $result['averageRating']);
        $this->assertSame([], $result['reviews']);
    }
}
