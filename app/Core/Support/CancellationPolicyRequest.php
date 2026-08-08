<?php

declare(strict_types=1);

namespace App\Core\Support;

use Carbon\CarbonInterface;

/**
 * The value passed through the `booking.cancellationPolicy` filter
 * pipeline — a normal transform-and-pass filter (like
 * `booking.priceCalculation`, unlike `booking.availabilityCheck`'s
 * short-circuit). `refundPercent` defaults to 100 (full refund) and each
 * pipe may lower it; if no pipe is registered, cancelling forfeits
 * nothing, which is a reasonable default in the absence of any configured
 * policy.
 *
 * Deliberately core-owned (moved here 2026-08-05, fixing a real Hard Rule
 * 1 violation — it was originally placed in booking-engine's own
 * namespace, and ViewBooking.php, a core Filament resource, imported it
 * directly). Consumed by both a core class and a plugin's filter pipe, so
 * it lives in core specifically so neither needs to depend on the other.
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
