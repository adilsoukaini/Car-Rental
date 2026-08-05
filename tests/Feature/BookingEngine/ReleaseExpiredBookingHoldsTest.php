<?php

namespace Tests\Feature\BookingEngine;

use App\Core\Contracts\PaymentGateway;
use App\Core\Support\FilterRegistry;
use App\Core\Support\PaymentGatewayRegistry;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Support\AvailabilityCheckRequest;
use Tests\TestCase;

class ReleaseExpiredBookingHoldsTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BookingEngineServiceProvider::class);

        $this->location = Location::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['location_id' => $this->location->id]);
    }

    public function test_the_command_is_genuinely_registered_by_the_plugin(): void
    {
        // Real proof the command exists as a callable Artisan command, not
        // just a class on disk — the same standard as every other
        // "route/resource is genuinely registered" test in this project.
        $this->assertContains(
            'bookings:release-expired-holds',
            array_keys(Artisan::all()),
        );
    }

    public function test_a_pending_booking_with_an_expired_hold_is_marked_expired_and_stops_blocking(): void
    {
        $booking = Booking::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'status' => 'pending',
            'hold_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('bookings:release-expired-holds')->assertExitCode(0);

        $booking->refresh();
        $this->assertSame('expired', $booking->status);
        $this->assertNull($booking->hold_expires_at);
    }

    public function test_a_pending_booking_whose_hold_has_not_expired_yet_is_left_alone(): void
    {
        $booking = Booking::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'status' => 'pending',
            'hold_expires_at' => now()->addMinutes(10),
        ]);

        $this->artisan('bookings:release-expired-holds');

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
        $this->assertNotNull($booking->hold_expires_at);
    }

    public function test_a_confirmed_booking_is_never_touched_even_if_it_somehow_has_a_past_hold_expiry(): void
    {
        $booking = Booking::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'status' => 'confirmed',
            'hold_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('bookings:release-expired-holds');

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
    }

    public function test_expiring_a_hold_releases_its_real_deposit_authorization_via_the_gateway(): void
    {
        $booking = Booking::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'status' => 'pending',
            'hold_expires_at' => now()->subMinute(),
        ]);

        $authorization = Payment::factory()->create([
            'booking_id' => $booking->id,
            'type' => 'deposit_authorization',
            'status' => 'authorized',
            'gateway' => 'stripe',
        ]);

        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('id')->andReturn('stripe');
        $gateway->shouldReceive('releaseDeposit')
            ->once()
            ->with(Mockery::on(fn (Payment $p) => $p->is($authorization)));
        PaymentGatewayRegistry::register($gateway, 'payments-stripe');

        $this->artisan('bookings:release-expired-holds');

        $booking->refresh();
        $this->assertSame('expired', $booking->status);
    }

    public function test_a_real_end_to_end_expiry_actually_frees_the_vehicle_for_the_same_dates(): void
    {
        // The consequence that actually matters, not just the status flip:
        // once expired, the same vehicle/dates must genuinely be bookable
        // again through the real availability check — same standard as
        // the cancellation phase's before/after proof. Starts with a
        // genuinely LIVE hold (confirmed blocking), then expires it, then
        // re-checks — not a hold that was already expired from the start.
        $booking = Booking::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'status' => 'pending',
            'hold_expires_at' => now()->addMinutes(15),
            'pickup_at' => now()->addDay(),
            'return_at' => now()->addDays(3),
        ]);

        $request = new AvailabilityCheckRequest(
            vehicleId: $this->vehicle->id,
            pickupAt: $booking->pickup_at,
            returnAt: $booking->return_at,
            pickupLocationId: $this->location->id,
        );

        $this->assertFalse(
            FilterRegistry::apply('booking.availabilityCheck', $request) !== false,
            'Expected the still-live hold to be blocking before it expires.',
        );

        $booking->update(['hold_expires_at' => now()->subMinute()]);

        $this->artisan('bookings:release-expired-holds');

        $this->assertTrue(
            FilterRegistry::apply('booking.availabilityCheck', $request) !== false,
            'Expected the same vehicle/dates to be available again after the hold expired and was released.',
        );
    }

    protected function tearDown(): void
    {
        PaymentGatewayRegistry::flush();

        parent::tearDown();
    }
}
