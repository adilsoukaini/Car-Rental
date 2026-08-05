<?php

namespace Tests\Feature\BookingEngine;

use App\Core\Support\CancellationPolicyRequest;
use App\Core\Support\FilterRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\BookingEngine\BookingEngineServiceProvider;
use Tests\TestCase;

/**
 * Exact-boundary tests for CoreCancellationPolicyPipe, same standard as
 * PriceCalculationTest — hand-computed expected percentages at each tier
 * boundary, not just "some discount applied". Config tiers as of this
 * writing: 7+ days = 100% refund, 2-6 days = 50%, under 2 days = 0%.
 */
class CancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BookingEngineServiceProvider::class);
    }

    private function refundPercentFor(string $pickupAt, string $cancelledAt): int
    {
        $request = new CancellationPolicyRequest(
            bookingId: 1,
            pickupAt: Carbon::parse($pickupAt),
            cancelledAt: Carbon::parse($cancelledAt),
        );

        /** @var CancellationPolicyRequest $result */
        $result = FilterRegistry::apply('booking.cancellationPolicy', $request);

        return $result->refundPercent;
    }

    public function test_exactly_seven_days_before_pickup_gets_a_full_refund(): void
    {
        $this->assertSame(100, $this->refundPercentFor('2026-09-15 10:00', '2026-09-08 10:00'));
    }

    public function test_one_day_short_of_seven_gets_the_lower_tier(): void
    {
        $this->assertSame(50, $this->refundPercentFor('2026-09-15 10:00', '2026-09-08 10:01'));
    }

    public function test_exactly_two_days_before_pickup_gets_the_fifty_percent_tier(): void
    {
        $this->assertSame(50, $this->refundPercentFor('2026-09-15 10:00', '2026-09-13 10:00'));
    }

    public function test_one_day_short_of_two_gets_zero_refund(): void
    {
        $this->assertSame(0, $this->refundPercentFor('2026-09-15 10:00', '2026-09-13 10:01'));
    }

    public function test_cancelling_the_same_day_as_pickup_gets_zero_refund(): void
    {
        $this->assertSame(0, $this->refundPercentFor('2026-09-15 10:00', '2026-09-15 02:00'));
    }

    public function test_cancelling_after_pickup_has_already_passed_gets_zero_refund(): void
    {
        $this->assertSame(0, $this->refundPercentFor('2026-09-15 10:00', '2026-09-16 10:00'));
    }

    public function test_far_in_advance_still_gets_the_top_tier(): void
    {
        $this->assertSame(100, $this->refundPercentFor('2026-12-01 10:00', '2026-09-08 10:00'));
    }
}
