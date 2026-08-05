<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Support;

use Carbon\CarbonInterface;

/**
 * The value passed through the `booking.cancellationPolicy` filter
 * pipeline — a normal transform-and-pass filter (like
 * `booking.priceCalculation`, unlike `booking.availabilityCheck`'s
 * short-circuit). `refundPercent` defaults to 100 (full refund) and each
 * pipe may lower it; if no pipe is registered, cancelling forfeits
 * nothing, which is a reasonable default in the absence of any configured
 * policy.
 */
class CancellationPolicyRequest
{
    public function __construct(
        public readonly int $bookingId,
        public readonly CarbonInterface $pickupAt,
        public readonly CarbonInterface $cancelledAt,
        public int $refundPercent = 100,
    ) {}
}
