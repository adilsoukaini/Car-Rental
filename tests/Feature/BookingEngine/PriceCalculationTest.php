<?php

namespace Tests\Feature\BookingEngine;

use App\Core\Support\FilterRegistry;
use App\Models\Location;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Support\BookingCreator;
use Plugins\BookingEngine\Support\PriceBreakdown;
use Plugins\BookingEngine\Support\PriceCalculationRequest;
use Tests\TestCase;

class PriceCalculationTest extends TestCase
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

    private function calculate(string $pickup, string $return): PriceBreakdown
    {
        $request = new PriceCalculationRequest(
            vehicleId: $this->vehicle->id,
            pickupAt: Carbon::parse($pickup),
            returnAt: Carbon::parse($return),
        );

        /** @var PriceBreakdown $result */
        $result = FilterRegistry::apply('booking.priceCalculation', new PriceBreakdown($request));

        return $result;
    }

    public function test_six_days_is_below_the_first_discount_tier(): void
    {
        $result = $this->calculate('2026-08-10 10:00', '2026-08-16 10:00');

        $this->assertSame(6, $result->days);
        $this->assertSame(0, $result->discountPercent);
        $this->assertSame(600.0, $result->subtotal);
        $this->assertSame(600.0, $result->totalPrice());
    }

    public function test_exactly_seven_days_hits_the_ten_percent_tier(): void
    {
        $result = $this->calculate('2026-08-10 10:00', '2026-08-17 10:00');

        $this->assertSame(7, $result->days);
        $this->assertSame(10, $result->discountPercent);
        // 100 * 7 * 0.9 = 630.00
        $this->assertSame(630.0, $result->subtotal);
    }

    public function test_twenty_nine_days_is_still_the_ten_percent_tier(): void
    {
        $result = $this->calculate('2026-08-01 10:00', '2026-08-30 10:00');

        $this->assertSame(29, $result->days);
        $this->assertSame(10, $result->discountPercent);
        // 100 * 29 * 0.9 = 2610.00
        $this->assertSame(2610.0, $result->subtotal);
    }

    public function test_exactly_thirty_days_hits_the_twenty_five_percent_tier(): void
    {
        $result = $this->calculate('2026-08-01 10:00', '2026-08-31 10:00');

        $this->assertSame(30, $result->days);
        $this->assertSame(25, $result->discountPercent);
        // 100 * 30 * 0.75 = 2250.00
        $this->assertSame(2250.0, $result->subtotal);
    }

    public function test_partial_day_rounds_up_to_the_next_full_day(): void
    {
        // 2 days + 3 hours = 51 hours -> ceil(51/24) = 3 days.
        $result = $this->calculate('2026-08-10 10:00', '2026-08-12 13:00');

        $this->assertSame(3, $result->days);
        $this->assertSame(0, $result->discountPercent);
        $this->assertSame(300.0, $result->subtotal);
    }

    public function test_exact_full_day_multiple_does_not_round_up_further(): void
    {
        // Exactly 48 hours -> exactly 2 days, not 3.
        $result = $this->calculate('2026-08-10 10:00', '2026-08-12 10:00');

        $this->assertSame(2, $result->days);
        $this->assertSame(200.0, $result->subtotal);
    }

    public function test_deposit_is_twenty_percent_of_the_discounted_subtotal(): void
    {
        // 7 days -> subtotal 630.00 -> deposit = 630 * 0.20 = 126.00
        $result = $this->calculate('2026-08-10 10:00', '2026-08-17 10:00');

        $this->assertSame(630.0, $result->subtotal);
        $this->assertSame(126.0, $result->depositAmount);
    }

    public function test_booking_creator_persists_the_computed_price_and_deposit(): void
    {
        $location = Location::factory()->create();
        $this->vehicle->update(['location_id' => $location->id]);

        $booking = app(BookingCreator::class)->create([
            'vehicle_id' => $this->vehicle->id,
            'guest_name' => 'Test Customer',
            'guest_email' => 'test@example.com',
            'guest_phone' => '0600000000',
            'pickup_location_id' => $location->id,
            'return_location_id' => $location->id,
            'pickup_at' => '2026-08-10 10:00',
            'return_at' => '2026-08-17 10:00',
            // Deliberately supplying a bogus price to prove BookingCreator
            // ignores caller input and computes its own.
            'total_price' => 1,
        ]);

        $fresh = $booking->fresh();

        $this->assertSame('630.00', $fresh->total_price);
        $this->assertSame('126.00', $fresh->security_deposit_amount);
    }
}
