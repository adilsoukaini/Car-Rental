<?php

namespace Tests\Feature\Models;

use App\Models\Booking;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_booking_has_a_null_user_id_and_populated_guest_fields(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_name' => 'Adil Test',
            'guest_email' => 'adil@example.com',
            'guest_phone' => '0600000000',
        ]);

        $fresh = $booking->fresh();

        $this->assertNull($fresh->user_id);
        $this->assertSame('Adil Test', $fresh->guest_name);
        $this->assertSame('adil@example.com', $fresh->guest_email);
        $this->assertSame('0600000000', $fresh->guest_phone);
    }

    public function test_booking_can_belong_to_a_registered_user_instead_of_a_guest(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->for($user)->create([
            'guest_name' => null,
            'guest_email' => null,
            'guest_phone' => null,
        ]);

        $this->assertTrue($booking->fresh()->user->is($user));
    }

    public function test_booking_supports_pickup_and_return_at_different_locations(): void
    {
        $pickup = Location::factory()->create(['name' => 'Casablanca Airport']);
        $return = Location::factory()->create(['name' => 'Rabat Downtown']);

        $booking = Booking::factory()->create([
            'pickup_location_id' => $pickup->id,
            'return_location_id' => $return->id,
        ]);

        $fresh = $booking->fresh();

        $this->assertNotSame($fresh->pickup_location_id, $fresh->return_location_id);
        $this->assertSame('Casablanca Airport', $fresh->pickupLocation->name);
        $this->assertSame('Rabat Downtown', $fresh->returnLocation->name);
    }

    public function test_booking_belongs_to_a_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $booking = Booking::factory()->for($vehicle)->create();

        $this->assertTrue($booking->fresh()->vehicle->is($vehicle));
    }

    public function test_deleting_a_vehicle_cascades_to_its_bookings(): void
    {
        $vehicle = Vehicle::factory()->create();
        $booking = Booking::factory()->for($vehicle)->create();

        $vehicle->delete();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_deleting_a_user_nullifies_the_bookings_user_id(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->for($user)->create();

        $user->delete();

        $this->assertNull($booking->fresh()->user_id);
    }

    public function test_booking_number_is_auto_generated_on_creation(): void
    {
        $booking = Booking::factory()->create();

        $fresh = $booking->fresh();

        $this->assertNotNull($fresh->booking_number);
        $this->assertSame(10, strlen($fresh->booking_number));
        $this->assertSame(strtoupper($fresh->booking_number), $fresh->booking_number);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $fresh->booking_number);
    }

    public function test_booking_number_is_unique_across_bookings(): void
    {
        $numbers = collect(range(1, 20))
            ->map(fn () => Booking::factory()->create()->fresh()->booking_number);

        $this->assertSame(20, $numbers->unique()->count());
    }
}
