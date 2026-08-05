<?php

namespace Tests\Feature\BookingEngine;

use App\Core\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Exceptions\VehicleNotAvailableException;
use Plugins\BookingEngine\Support\BookingCreator;
use Tests\TestCase;

/**
 * Direct tests of BookingCreator::createPending()/confirmPending() —
 * BookingCheckoutTest already covers the same lifecycle through the real
 * HTTP controller with a mocked gateway; these test the service layer in
 * isolation, the same split AvailabilityCheckTest/PriceCalculationTest use
 * for create().
 */
class PendingBookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BookingEngineServiceProvider::class);

        $this->location = Location::factory()->create();
        $this->vehicle = Vehicle::factory()->create([
            'location_id' => $this->location->id,
            'daily_rate' => 200,
            'category' => 'no-age-restriction-category',
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(): array
    {
        return [
            'vehicle_id' => $this->vehicle->id,
            'user_id' => null,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '0600000000',
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'pickup_at' => now()->addDay(),
            'return_at' => now()->addDays(3),
        ];
    }

    public function test_create_pending_persists_a_pending_booking_with_a_future_hold_expiry_and_no_dispatch(): void
    {
        Event::fake([BookingConfirmed::class]);

        $booking = app(BookingCreator::class)->createPending($this->attributes());

        $this->assertSame('pending', $booking->status);
        $this->assertNotNull($booking->hold_expires_at);
        $this->assertTrue($booking->hold_expires_at->isFuture());
        $this->assertEqualsWithDelta(
            (int) config('booking-engine.hold_ttl_minutes', 15),
            now()->diffInMinutes($booking->hold_expires_at),
            1,
        );

        Event::assertNotDispatched(BookingConfirmed::class);
    }

    public function test_confirm_pending_transitions_to_confirmed_and_dispatches(): void
    {
        Event::fake([BookingConfirmed::class]);

        $booking = app(BookingCreator::class)->createPending($this->attributes());

        $confirmed = app(BookingCreator::class)->confirmPending($booking);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertNull($confirmed->hold_expires_at);

        Event::assertDispatched(BookingConfirmed::class, fn (BookingConfirmed $event) => $event->booking->is($confirmed));
    }

    public function test_confirm_pending_is_idempotent_and_does_not_redispatch(): void
    {
        Event::fake([BookingConfirmed::class]);

        $booking = app(BookingCreator::class)->createPending($this->attributes());
        app(BookingCreator::class)->confirmPending($booking);

        Event::assertDispatched(BookingConfirmed::class, 1);

        $again = app(BookingCreator::class)->confirmPending($booking->fresh());

        $this->assertSame('confirmed', $again->status);
        Event::assertDispatched(BookingConfirmed::class, 1);
    }

    public function test_confirm_pending_rejects_if_the_vehicle_became_unavailable_in_the_meantime(): void
    {
        $booking = app(BookingCreator::class)->createPending($this->attributes());

        // Simulate a checked_out booking somehow taking the same vehicle
        // and dates in the meantime (e.g. an admin manually overriding).
        Booking::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'status' => 'checked_out',
            'pickup_at' => $booking->pickup_at,
            'return_at' => $booking->return_at,
        ]);

        $this->expectException(VehicleNotAvailableException::class);

        app(BookingCreator::class)->confirmPending($booking);
    }

    public function test_create_still_confirms_immediately_with_no_hold(): void
    {
        Event::fake([BookingConfirmed::class]);

        $booking = app(BookingCreator::class)->create($this->attributes());

        $this->assertSame('confirmed', $booking->status);
        $this->assertNull($booking->hold_expires_at);

        Event::assertDispatched(BookingConfirmed::class);
    }
}
