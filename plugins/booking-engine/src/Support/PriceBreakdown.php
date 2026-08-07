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
        /** Promo-code discount applied to $subtotal (0.0 when none). Set by the promotions plugin's PromoCodePipe. */
        public float $promoDiscount = 0.0,
        /** Set to true by PromoCodePipe only when a supplied code was genuinely applied. */
        public bool $promoApplied = false,
        /** Human-readable error for an invalid/expired/over-limit promo code, set by PromoCodePipe. Null = no error. */
        public ?string $promoError = null,
    ) {}

    public function totalPrice(): float
    {
        return $this->subtotal;
    }
}
