<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Support;

/**
 * The value passed through the `booking.priceCalculation` filter pipeline.
 *
 * Unlike `booking.availabilityCheck`, this filter transforms rather than
 * short-circuits — each pipe fills in more of the breakdown and calls
 * $next($breakdown). `totalPrice()` is the rental charge only; the
 * security deposit is a deliberately separate figure, never added into it
 * (see docs/03-DOMAIN-REQUIREMENTS.md's "separate from the rental charge"
 * wording) — this plugin never charges either figure, it only computes them.
 */
class PriceBreakdown
{
    public function __construct(
        public readonly PriceCalculationRequest $request,
        public float $dailyRate = 0.0,
        public int $days = 0,
        public int $discountPercent = 0,
        public float $subtotal = 0.0,
        public float $depositAmount = 0.0,
    ) {}

    public function totalPrice(): float
    {
        return $this->subtotal;
    }
}
