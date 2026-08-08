<?php

namespace Tests\Feature\DriverVerification;

use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Support\BookingCreator;
use Plugins\DriverVerification\DriverVerificationServiceProvider;
use Tests\TestCase;

/**
 * Regression guard for the 2026-08-08 change: online driver-license
 * verification is now OPTIONAL ("pre-verification") and never gates a
 * booking. Minimum-age and license requirements are disclosed on the
 * vehicle detail / checkout pages and verified by the rental agent at
 * pickup — `BookingCreator` performs no eligibility check at all.
 *
 * Before this change, a registered user without an `approved` verification
 * was blocked from booking an age-restricted category (DriverNotEligibleException).
 * Every test here asserts the opposite: verification status never affects
 * whether a booking can be created.
 */
class DriverVerificationDoesNotBlockBookingTest extends TestCase
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

    public function test_guest_can_book_a_luxury_vehicle_without_any_verification(): void
    {
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'luxury']);

        $booking = app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, null, '2026-09-01 10:00'),
        );

        $this->assertSame('confirmed', $booking->status);
    }

    public function test_registered_user_with_no_verification_can_book_any_category(): void
    {
        foreach (['economy', 'suv', 'van', 'luxury'] as $category) {
            $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => $category]);
            $user = User::factory()->create(['role' => 'customer']);

            $booking = app(BookingCreator::class)->create(
                $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
            );

            $this->assertSame('confirmed', $booking->status, "Failed for category {$category}");
        }
    }

    public function test_registered_user_with_a_pending_verification_can_book(): void
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

        $booking = app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
        );

        $this->assertSame('confirmed', $booking->status);
    }

    public function test_registered_user_under_the_minimum_age_is_still_allowed(): void
    {
        // economy requires 21 per config. Born such that they are 18 at pickup —
        // previously blocked, now allowed (the agent verifies age at pickup).
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'economy']);
        $user = User::factory()->create(['role' => 'customer']);
        $user->driverVerifications()->create([
            'license_number' => 'X123',
            'license_country' => 'Morocco',
            'date_of_birth' => '2007-09-02', // 18 at pickup
            'license_document_path' => 'x.jpg',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $booking = app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
        );

        $this->assertSame('confirmed', $booking->status);
    }

    public function test_rejected_verification_does_not_block_booking(): void
    {
        $vehicle = Vehicle::factory()->create(['location_id' => $this->location->id, 'category' => 'luxury']);
        $user = User::factory()->create(['role' => 'customer']);
        $user->driverVerifications()->create([
            'license_number' => 'X123',
            'license_country' => 'Morocco',
            'date_of_birth' => '1990-01-01',
            'license_document_path' => 'x.jpg',
            'status' => 'rejected',
            'rejection_reason' => 'Blurry photo',
        ]);

        $booking = app(BookingCreator::class)->create(
            $this->bookingAttributes($vehicle->id, $user->id, '2026-09-01 10:00'),
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
}
