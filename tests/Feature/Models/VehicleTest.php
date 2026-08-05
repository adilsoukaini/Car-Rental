<?php

namespace Tests\Feature\Models;

use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_belongs_to_a_location(): void
    {
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->for($location)->create();

        $this->assertTrue($vehicle->location->is($location));
    }

    public function test_duplicate_license_plate_is_rejected_at_the_database_level(): void
    {
        Vehicle::factory()->create(['license_plate' => '12345-A-6']);

        $this->expectException(UniqueConstraintViolationException::class);

        Vehicle::factory()->create(['license_plate' => '12345-A-6']);
    }

    public function test_deleting_a_location_nullifies_the_vehicles_location_id(): void
    {
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->for($location)->create();

        $location->delete();

        $this->assertNull($vehicle->fresh()->location_id);
    }
}
