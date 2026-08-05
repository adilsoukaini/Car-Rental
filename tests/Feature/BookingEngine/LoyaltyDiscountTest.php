<?php

namespace Tests\Feature\BookingEngine;

use App\Core\Support\FilterRegistry;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Support\PriceBreakdown;
use Plugins\BookingEngine\Support\PriceCalculationRequest;
use Tests\TestCase;

class LoyaltyDiscountTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BookingEngineServiceProvider::class);

        // Round daily_rate for easy exact-number assertions.
        $this->vehicle = Vehicle::factory()->create(['daily_rate' => 100.00]);
    }

    /** @return array{0: PriceBreakdown, 1: array} */
    private function calculate(?int $userId, string $pickup = '2026-08-10 10:00', string $return = '2026-08-16 10:00'): PriceBreakdown
    {
        $request = new PriceCalculationRequest(
            vehicleId: $this->vehicle->id,
            pickupAt: Carbon::parse($pickup),
            returnAt: Carbon::parse($return),
            userId: $userId,
        );

        /** @var PriceBreakdown $result */
        $result = FilterRegistry::apply('booking.priceCalculation', new PriceBreakdown($request));

        return $result;
    }

    private function createReturnedBookings(User $user, int $count): void
    {
        Booking::factory()->count($count)->create([
            'user_id' => $user->id,
            'status' => 'returned',
        ]);
    }

    public function test_guest_bookings_never_receive_a_loyalty_discount(): void
    {
        // A guest has no user_id at all, so there is no history to count
        // against regardless of how many rentals happened under the same
        // guest_email — exempt by construction, not by an explicit check.
        $result = $this->calculate(userId: null);

        $this->assertSame(0, $result->discountPercent);
    }

    public function test_two_prior_returned_rentals_is_below_the_first_tier(): void
    {
        $user = User::factory()->create();
        $this->createReturnedBookings($user, 2);

        // 6-day booking: below the duration-discount tier too, isolating
        // the loyalty tier's own boundary.
        $result = $this->calculate($user->id);

        $this->assertSame(0, $result->discountPercent);
        $this->assertSame(600.0, $result->subtotal);
    }

    public function test_exactly_three_prior_returned_rentals_hits_the_five_percent_tier(): void
    {
        $user = User::factory()->create();
        $this->createReturnedBookings($user, 3);

        $result = $this->calculate($user->id);

        $this->assertSame(5, $result->discountPercent);
        // 100 * 6 * 0.95 = 570.00
        $this->assertSame(570.0, $result->subtotal);
    }

    public function test_exactly_ten_prior_returned_rentals_hits_the_fifteen_percent_tier(): void
    {
        $user = User::factory()->create();
        $this->createReturnedBookings($user, 10);

        $result = $this->calculate($user->id);

        $this->assertSame(15, $result->discountPercent);
        // 100 * 6 * 0.85 = 510.00
        $this->assertSame(510.0, $result->subtotal);
    }

    public function test_only_returned_bookings_count_toward_the_tier(): void
    {
        $user = User::factory()->create();
        $this->createReturnedBookings($user, 2);
        // These should never count, no matter how many exist.
        Booking::factory()->count(5)->create(['user_id' => $user->id, 'status' => 'confirmed']);
        Booking::factory()->count(5)->create(['user_id' => $user->id, 'status' => 'pending']);
        Booking::factory()->count(5)->create(['user_id' => $user->id, 'status' => 'cancelled']);

        // Still only 2 returned -> still below the first tier.
        $result = $this->calculate($user->id);

        $this->assertSame(0, $result->discountPercent);
    }

    public function test_duration_discount_wins_when_it_is_the_larger_of_the_two(): void
    {
        $user = User::factory()->create();
        // 3 prior rentals -> 5% loyalty tier.
        $this->createReturnedBookings($user, 3);

        // 30-day booking -> 25% duration tier, strictly larger than 5%.
        $result = $this->calculate($user->id, '2026-08-01 10:00', '2026-08-31 10:00');

        $this->assertSame(25, $result->discountPercent);
        // 100 * 30 * 0.75 = 2250.00 -- the discounts must NOT stack (would
        // be 2100.00 at 30% if they did).
        $this->assertSame(2250.0, $result->subtotal);
    }

    public function test_loyalty_discount_wins_when_it_is_the_larger_of_the_two(): void
    {
        $user = User::factory()->create();
        // 10 prior rentals -> 15% loyalty tier.
        $this->createReturnedBookings($user, 10);

        // 7-day booking -> only a 10% duration tier, strictly smaller than 15%.
        $result = $this->calculate($user->id, '2026-08-10 10:00', '2026-08-17 10:00');

        $this->assertSame(15, $result->discountPercent);
        // 100 * 7 * 0.85 = 595.00 -- not the 10%-tier's 630.00, and not a
        // stacked 25% either.
        $this->assertSame(595.0, $result->subtotal);
    }
}
