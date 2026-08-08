<?php

namespace Tests\Feature\DriverVerification;

use App\Core\Support\DriverEligibilityCheckRequest;
use App\Core\Support\FilterRegistry;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Exceptions\DriverNotEligibleException;
use Plugins\BookingEngine\Support\BookingCreator;
use Plugins\DriverVerification\DriverVerificationServiceProvider;
use Tests\TestCase;

class DriverEligibilityCheckTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BookingEngineServiceProvider::class);
        $this->app->register(DriverVerificationServiceProvider::class);

        // driver-verification is the first plugin with its own migrations —
        // RefreshDatabase (via parent::setUp()) only migrates core paths
        // known BEFORE this test registers the plugin, so its migration
        // must be run explicitly here.
        $this->artisan('migrate', ['--path' => 'plugins/driver-verification/database/migrations']);

        $this->location = Location::factory()->create();
    }

    /** @return array<string, mixed> */
    private function bookingAttributes(int $vehicleId, ?int $userId, string $pickupAt): array
    {
        return [
            'vehicle_id' => $vehicleId,
            'user_id' => $userId,
            'guest_name' => $userId === null ? 'Guest' : null,
            'guest_email' => $userId === null ? 'guest@example.com' : null,
            'guest_phone' => $userId === null ? '0600000000' : null,
            'pickup_location_id' => $this->location->id,
            'return_location_id' => $this->location->id,
            'pickup_at' => $pickupAt,
            'return_at' => Carbon::parse($pickupAt)->addDays(2)->toDateTimeString(),
        ];
    }

    public function test_guest_booking_is_exempt_from_driver_verification(): void
    {
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'luxury']);

        $booking = app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, null, '2026-09-01 10:00'),
        );

        $this->assertSame('confirmed', $booking->status);
    }

    public function test_category_with_no_configured_minimum_age_is_unaffected(): void
    {
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'unlisted-category']);
        $user = User::factory()->create(['role' => 'customer']);

        $booking = app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
        );

        $this->assertSame('confirmed', $booking->status);
    }

    public function test_registered_user_with_no_verification_is_blocked_from_an_age_restricted_category(): void
    {
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'economy']);
        $user = User::factory()->create(['role' => 'customer']);

        $this->expectException(DriverNotEligibleException::class);

        app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
        );
    }

    public function test_pending_unapproved_verification_does_not_grant_eligibility(): void
    {
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'economy']);
        $user = User::factory()->create(['role' => 'customer']);
        $user->driverVerifications()->create([
            'license_number' => 'X123',
            'license_country' => 'Morocco',
            'date_of_birth' => '1990-01-01',
            'license_document_path' => 'x.jpg',
            'status' => 'pending',
        ]);

        $this->expectException(DriverNotEligibleException::class);

        app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
        );
    }

    public function test_approved_but_too_young_at_pickup_is_blocked(): void
    {
        // economy requires 21. Born such that they are 20 at pickup.
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'economy']);
        $user = User::factory()->create(['role' => 'customer']);
        $user->driverVerifications()->create([
            'license_number' => 'X123',
            'license_country' => 'Morocco',
            'date_of_birth' => '2006-09-02', // turns 20 on 2026-09-02, still 19 on pickup
            'license_document_path' => 'x.jpg',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $this->expectException(DriverNotEligibleException::class);

        app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
        );
    }

    public function test_approved_and_exactly_meets_minimum_age_at_pickup_is_allowed(): void
    {
        // economy requires 21. Born exactly 21 years before pickup date.
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'economy']);
        $user = User::factory()->create(['role' => 'customer']);
        $user->driverVerifications()->create([
            'license_number' => 'X123',
            'license_country' => 'Morocco',
            'date_of_birth' => '2005-09-01', // turns exactly 21 on 2026-09-01, the pickup date
            'license_document_path' => 'x.jpg',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $booking = app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
        );

        $this->assertSame('confirmed', $booking->status);
    }

    public function test_approved_and_old_enough_is_allowed_for_luxury_category(): void
    {
        // luxury requires 25.
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'luxury']);
        $user = User::factory()->create(['role' => 'customer']);
        $user->driverVerifications()->create([
            'license_number' => 'X123',
            'license_country' => 'Morocco',
            'date_of_birth' => '1995-01-01',
            'license_document_path' => 'x.jpg',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $booking = app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
        );

        $this->assertSame('confirmed', $booking->status);
    }

    public function test_eligibility_check_alone_respects_the_short_circuit_convention(): void
    {
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'economy']);
        $user = User::factory()->create(['role' => 'customer']);

        $request = new DriverEligibilityCheckRequest(
            userId: $user->id,
            vehicleCategory: 'economy',
            pickupAt: Carbon::parse('2026-09-01 10:00'),
        );

        $result = FilterRegistry::apply('booking.driverEligibilityCheck', $request);

        $this->assertFalse($result);
    }
}
