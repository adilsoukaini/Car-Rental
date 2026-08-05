<?php

namespace Tests\Feature\Models;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_has_many_vehicles(): void
    {
        $location = Location::factory()->create();
        Vehicle::factory()->count(3)->for($location)->create();

        $this->assertCount(3, $location->vehicles);
    }

    public function test_location_tracks_pickup_and_return_bookings_separately(): void
    {
        $casa = Location::factory()->create();
        $rabat = Location::factory()->create();

        $booking = Booking::factory()->create([
            'pickup_location_id' => $casa->id,
            'return_location_id' => $rabat->id,
        ]);

        $this->assertTrue($casa->pickupBookings->contains($booking));
        $this->assertTrue($rabat->returnBookings->contains($booking));
        $this->assertFalse($casa->returnBookings->contains($booking));
    }
}
