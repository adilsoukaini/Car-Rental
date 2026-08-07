<?php

namespace Tests\Feature\Promotions;

use App\Core\Support\FilterRegistry;
use App\Models\Location;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Plugins\BookingEngine\Support\BookingCreator;
use Plugins\BookingEngine\Support\PriceBreakdown;
use Plugins\BookingEngine\Support\PriceCalculationRequest;
use Plugins\Promotions\Models\PromoCode;
use Plugins\Promotions\PromotionsServiceProvider;
use Tests\TestCase;

/**
 * Promo/discount codes end to end: the PromoCodePipe on
 * booking.priceCalculation (exact-number math, all four validation
 * failures), the booking-creation path (discounted total_price + promo_code
 * recorded in metadata only when actually applied), and the uses_count
 * increment at BookingConfirmed — with a guard that read-only price previews
 * never consume a usage.
 */
class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BookingEngineServiceProvider::class);
        $this->app->register(PromotionsServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'plugins/promotions/database/migrations']);

        $this->vehicle = Vehicle::factory()->create(['daily_rate' => 100.00]);
    }

    private function calculate(
        ?string $promoCode,
        string $pickup = '2026-08-10 10:00',
        string $return = '2026-08-16 10:00',
    ): PriceBreakdown {
        $request = new PriceCalculationRequest(
            vehicleId: $this->vehicle->id,
            pickupAt: Carbon::parse($pickup),
            returnAt: Carbon::parse($return),
            promoCode: $promoCode,
        );

        /** @var PriceBreakdown $result */
        $result = FilterRegistry::apply('booking.priceCalculation', new PriceBreakdown($request));

        return $result;
    }

    public function test_percentage_promo_applies_to_subtotal(): void
    {
        PromoCode::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);

        // 6 days * 100 = 600, 10% off = 540.
        $result = $this->calculate('WELCOME10');

        $this->assertSame(540.0, $result->subtotal);
        $this->assertSame(60.0, $result->promoDiscount);
        $this->assertTrue($result->promoApplied);
        $this->assertNull($result->promoError);
    }

    public function test_fixed_amount_promo_applies_to_subtotal(): void
    {
        PromoCode::create(['code' => 'FIX100', 'type' => 'fixed', 'value' => 100]);

        // 600 - 100 = 500.
        $result = $this->calculate('FIX100');

        $this->assertSame(500.0, $result->subtotal);
        $this->assertSame(100.0, $result->promoDiscount);
    }

    public function test_promo_applies_after_duration_discount_not_before(): void
    {
        PromoCode::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);

        // 30 days at 100 = 3000, 25% duration tier = 2250, then 10% promo = 2025.
        $result = $this->calculate('WELCOME10', '2026-08-01 10:00', '2026-08-31 10:00');

        $this->assertSame(25, $result->discountPercent);
        $this->assertSame(2025.0, $result->subtotal);
        $this->assertSame(225.0, $result->promoDiscount);
    }

    public function test_deposit_is_computed_on_promo_discounted_subtotal(): void
    {
        PromoCode::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);

        // Promo runs before CoreDepositPipe (priority 17 < 20), so the
        // deposit is 20% of the 540 discounted subtotal, not of 600.
        $result = $this->calculate('WELCOME10');

        $this->assertSame(540.0, $result->subtotal);
        $this->assertSame(108.0, $result->depositAmount);
    }

    public function test_no_code_returns_unchanged_price(): void
    {
        $result = $this->calculate(null);

        $this->assertSame(600.0, $result->subtotal);
        $this->assertSame(0.0, $result->promoDiscount);
        $this->assertFalse($result->promoApplied);
        $this->assertNull($result->promoError);
    }

    public function test_unknown_code_sets_error_and_applies_no_discount(): void
    {
        $result = $this->calculate('NOPE');

        $this->assertSame(600.0, $result->subtotal);
        $this->assertSame(0.0, $result->promoDiscount);
        $this->assertNotNull($result->promoError);
    }

    public function test_inactive_code_is_treated_as_invalid(): void
    {
        PromoCode::create(['code' => 'OFF', 'type' => 'percentage', 'value' => 10, 'is_active' => false]);

        $result = $this->calculate('OFF');

        $this->assertSame(600.0, $result->subtotal);
        $this->assertNotNull($result->promoError);
    }

    public function test_expired_code_sets_error_and_applies_no_discount(): void
    {
        PromoCode::create(['code' => 'OLD', 'type' => 'percentage', 'value' => 10, 'expires_at' => Carbon::yesterday()]);

        $result = $this->calculate('OLD');

        $this->assertSame(600.0, $result->subtotal);
        $this->assertNotNull($result->promoError);
    }

    public function test_over_limit_code_sets_error_and_applies_no_discount(): void
    {
        PromoCode::create(['code' => 'LIMIT', 'type' => 'percentage', 'value' => 10, 'max_uses' => 2, 'uses_count' => 2]);

        $result = $this->calculate('LIMIT');

        $this->assertSame(600.0, $result->subtotal);
        $this->assertNotNull($result->promoError);
    }

    public function test_below_minimum_amount_sets_error_and_applies_no_discount(): void
    {
        PromoCode::create(['code' => 'MIN', 'type' => 'percentage', 'value' => 10, 'min_booking_amount' => 1000]);

        $result = $this->calculate('MIN');

        $this->assertSame(600.0, $result->subtotal);
        $this->assertNotNull($result->promoError);
    }

    public function test_fixed_amount_never_discounts_below_zero(): void
    {
        PromoCode::create(['code' => 'BIG', 'type' => 'fixed', 'value' => 99999]);

        $result = $this->calculate('BIG');

        $this->assertSame(0.0, $result->subtotal);
        $this->assertSame(600.0, $result->promoDiscount);
    }

    public function test_preview_never_increments_usage_count(): void
    {
        $promo = PromoCode::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10, 'max_uses' => 5]);

        // Run the pipeline twice — read-only previews must not consume uses,
        // or a customer refreshing the checkout page would burn the code.
        $this->calculate('WELCOME10');
        $this->calculate('WELCOME10');

        $this->assertSame(0, $promo->fresh()->uses_count);
    }

    public function test_booking_created_with_promo_has_discounted_price_and_records_metadata(): void
    {
        PromoCode::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create(['daily_rate' => 100.00, 'location_id' => $location->id]);

        $booking = app(BookingCreator::class)->create([
            'vehicle_id' => $vehicle->id,
            'pickup_location_id' => $location->id,
            'return_location_id' => $location->id,
            'pickup_at' => '2026-08-10 10:00',
            'return_at' => '2026-08-16 10:00',
            'promo_code' => 'WELCOME10',
        ]);

        // 6 days * 100 = 600, 10% off = 540 — and the code is recorded on the
        // booking's metadata so the BookingConfirmed listener can count it.
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(540.0, (float) $booking->total_price);
        $this->assertSame('WELCOME10', $booking->metadata['promo_code'] ?? null);

        // BookingConfirmed fires inside create() -> the listener bumps uses_count.
        $this->assertSame(1, PromoCode::where('code', 'WELCOME10')->value('uses_count'));
    }

    public function test_invalid_promo_does_not_get_recorded_on_booking_metadata(): void
    {
        PromoCode::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10, 'max_uses' => 1, 'uses_count' => 1]);
        $location = Location::factory()->create();
        $vehicle = Vehicle::factory()->create(['daily_rate' => 100.00, 'location_id' => $location->id]);

        $booking = app(BookingCreator::class)->create([
            'vehicle_id' => $vehicle->id,
            'pickup_location_id' => $location->id,
            'return_location_id' => $location->id,
            'pickup_at' => '2026-08-10 10:00',
            'return_at' => '2026-08-16 10:00',
            'promo_code' => 'WELCOME10',
        ]);

        // The code was over its limit, so no discount was applied and it must
        // NOT be recorded on the metadata (and thus not counted by the
        // BookingConfirmed listener).
        $this->assertSame(600.0, (float) $booking->total_price);
        $this->assertArrayNotHasKey('promo_code', $booking->metadata ?? []);
        $this->assertSame(1, PromoCode::where('code', 'WELCOME10')->value('uses_count'));
    }
}
