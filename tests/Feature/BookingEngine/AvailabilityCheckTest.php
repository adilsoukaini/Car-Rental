<?php

namespace Tests\Feature\BookingEngine;

use App\Core\Support\FilterRegistry;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Exceptions\VehicleNotAvailableException;
use Plugins\BookingEngine\Support\AvailabilityCheckRequest;
use Plugins\BookingEngine\Support\BookingCreator;
use Tests\TestCase;

class AvailabilityCheckTest extends TestCase
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

    private function existingBooking(string $pickup, string $return, string $status = 'confirmed'): Booking
    {
        return Booking::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'pickup_at' => Carbon::parse($pickup),
            'return_at' => Carbon::parse($return),
            'status' => $status,
        ]);
    }

    private function isAvailable(string $pickup, string $return, ?int $locationId = null): bool
    {
        $request = new AvailabilityCheckRequest(
            vehicleId: $this->vehicle->id,
            pickupAt: Carbon::parse($pickup),
            returnAt: Carbon::parse($return),
            pickupLocationId: $locationId ?? $this->location->id,
        );

        return FilterRegistry::apply('booking.availabilityCheck', $request) !== false;
    }

    public function test_exact_duplicate_range_is_blocked(): void
    {
        $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00');

        $this->assertFalse($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_new_range_fully_containing_existing_is_blocked(): void
    {
        $this->existingBooking('2026-08-10 10:00', '2026-08-11 10:00');

        $this->assertFalse($this->isAvailable('2026-08-09 10:00', '2026-08-12 10:00'));
    }

    public function test_existing_range_fully_containing_new_is_blocked(): void
    {
        $this->existingBooking('2026-08-09 10:00', '2026-08-12 10:00');

        $this->assertFalse($this->isAvailable('2026-08-10 10:00', '2026-08-11 10:00'));
    }

    public function test_partial_overlap_at_start_is_blocked(): void
    {
        $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00');

        $this->assertFalse($this->isAvailable('2026-08-08 10:00', '2026-08-11 10:00'));
    }

    public function test_partial_overlap_at_end_is_blocked(): void
    {
        $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00');

        $this->assertFalse($this->isAvailable('2026-08-11 10:00', '2026-08-14 10:00'));
    }

    public function test_new_booking_starting_exactly_when_existing_ends_is_allowed(): void
    {
        $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00');

        $this->assertTrue($this->isAvailable('2026-08-12 10:00', '2026-08-14 10:00'));
    }

    public function test_new_booking_ending_exactly_when_existing_starts_is_allowed(): void
    {
        $this->existingBooking('2026-08-12 10:00', '2026-08-14 10:00');

        $this->assertTrue($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_non_overlapping_range_on_the_same_vehicle_is_allowed(): void
    {
        $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00');

        $this->assertTrue($this->isAvailable('2026-09-01 10:00', '2026-09-03 10:00'));
    }

    public function test_overlapping_range_on_a_different_vehicle_is_allowed(): void
    {
        $otherVehicle = Vehicle::factory()->create(['location_id' => $this->location->id]);
        Booking::factory()->create([
            'vehicle_id' => $otherVehicle->id,
            'pickup_at' => Carbon::parse('2026-08-10 10:00'),
            'return_at' => Carbon::parse('2026-08-12 10:00'),
            'status' => 'confirmed',
        ]);

        $this->assertTrue($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_overlapping_pending_booking_with_no_hold_expiry_does_not_block(): void
    {
        // A pending row with hold_expires_at === null has no defined "is
        // this hold still live" answer (see CoreAvailabilityCheckPipe's
        // docblock) — this pipe never guesses, so it doesn't block. In
        // practice this shape only comes from BookingCreator::create()'s
        // older immediate-confirm path, which never actually persists a
        // pending row at all — this is a defensive case, not a real one.
        $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00', 'pending');

        $this->assertTrue($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_overlapping_pending_booking_with_a_live_hold_blocks(): void
    {
        // The 2026-08-04 revision: a pending booking with a real, still-live
        // hold (hold_expires_at in the future) now blocks — this is what
        // makes the double-hold race structurally impossible for the real
        // checkout flow. See CoreAvailabilityCheckPipe's docblock and
        // CLAUDE.md's "deposit-gate" section for the full reasoning.
        $booking = $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00', 'pending');
        $booking->update(['hold_expires_at' => now()->addMinutes(15)]);

        $this->assertFalse($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_overlapping_pending_booking_with_an_expired_hold_does_not_block(): void
    {
        // Boundary: the moment hold_expires_at is in the past, this pipe
        // treats the hold as no longer live — even before
        // ReleaseExpiredBookingHolds has actually run and flipped the row
        // to 'expired'. This matters for real correctness: the scheduled
        // job runs once a minute, so there's a real window where a
        // pending row is stale but not yet cleaned up; a new booking
        // attempt must not be blocked by it during that window.
        $booking = $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00', 'pending');
        $booking->update(['hold_expires_at' => now()->subMinute()]);

        $this->assertTrue($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_overlapping_pending_booking_with_a_hold_expiring_this_instant_does_not_block(): void
    {
        // Exact boundary: hold_expires_at exactly equal to "now" is treated
        // as already expired (the pipe's condition is a strict `>`, not
        // `>=`) — a hold's last valid instant is the instant strictly
        // before its expiry, matching the same exclusive-end convention
        // already used for the overlap query itself.
        $booking = $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00', 'pending');
        $now = now();
        $booking->update(['hold_expires_at' => $now]);

        $this->assertTrue($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_overlapping_cancelled_booking_does_not_block(): void
    {
        $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00', 'cancelled');

        $this->assertTrue($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_overlapping_checked_out_booking_blocks(): void
    {
        $this->existingBooking('2026-08-10 10:00', '2026-08-12 10:00', 'checked_out');

        $this->assertFalse($this->isAvailable('2026-08-10 10:00', '2026-08-12 10:00'));
    }

    public function test_location_mismatch_blocks_even_with_free_dates(): void
    {
        $otherLocation = Location::factory()->create();

        $this->assertFalse($this->isAvailable('2026-09-01 10:00', '2026-09-03 10:00', $otherLocation->id));
    }

    public function test_inactive_pickup_location_blocks_even_with_free_dates(): void
    {
        $this->location->update(['is_active' => false]);

        $this->assertFalse($this->isAvailable('2026-09-01 10:00', '2026-09-03 10:00'));
    }

    public function test_second_overlapping_booking_creation_fails_after_first_succeeds(): void
    {
        $creator = app(BookingCreator::class);

        $attributes = [
            'vehicle_id' => $this->vehicle->id,
            'guest_name' => 'First Customer',
            'guest_email' => 'first@example.com',
            'guest_phone' => '0600000000',
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'pickup_at' => '2026-08-10 10:00',
            'return_at' => '2026-08-12 10:00',
            'total_price' => 900,
        ];

        $first = $creator->create($attributes);
        $this->assertSame('confirmed', $first->status);

        $this->expectException(VehicleNotAvailableException::class);

        $creator->create([
            ...$attributes,
            'guest_name' => 'Second Customer',
            'guest_email' => 'second@example.com',
        ]);
    }
}
