<?php

declare(strict_types=1);

namespace Plugins\Promotions\Filters;

use Closure;
use Plugins\BookingEngine\Support\PriceBreakdown;
use Plugins\Promotions\Models\PromoCode;

/**
 * Applies a promo/discount code to the booking's price by reading
 * `promo_code` off the PriceCalculationRequest and discounting the running
 * subtotal. Registered into the booking-engine-owned `booking.priceCalculation`
 * filter at priority 17 — after CoreDurationDiscountPipe (10) and
 * CoreLoyaltyDiscountPipe (15) have set the base subtotal, and before
 * CoreDepositPipe (20) so the security deposit is computed as a percentage
 * of the final, promo-discounted subtotal. Cross-plugin communication
 * happens entirely through that filter contract (Hard Rule 2) — the pipe
 * never imports booking-engine outside its handle() signature, and
 * booking-engine never references the promotions namespace.
 *
 * A valid code reduces `$breakdown->subtotal` and records the discount in
 * `$breakdown->promoDiscount` (plus `promoApplied = true` so callers can
 * tell a genuinely-applied code from a merely-present one). An invalid,
 * expired, over-limit, or below-minimum code is silently left unapplied
 * (the pricing pipeline must stay robust — an unbookable code means "no
 * discount," not a broken checkout) with a human-readable `promoError`
 * set for the UI to surface.
 *
 * Deliberately does NOT increment uses_count here: this pipe runs during
 * read-only price previews too, and a preview must never consume a usage.
 * uses_count is incremented once, when a booking carrying this code is
 * actually CONFIRMED, by the plugin's `BookingConfirmed` listener
 * (IncrementPromoCodeUsage) — the same "validate at quote, increment at
 * order" split the e-commerce project's CheckoutController uses.
 */
class PromoCodePipe
{
    public function handle(PriceBreakdown $breakdown, Closure $next): mixed
    {
        $code = $breakdown->request->promoCode;

        if ($code === null || trim($code) === '') {
            return $next($breakdown);
        }

        $promo = PromoCode::query()
            ->whereRaw('LOWER(code) = ?', [strtolower(trim($code))])
            ->where('is_active', true)
            ->first();

        if ($promo === null) {
            $breakdown->promoError = 'This code is not valid or is inactive.';

            return $next($breakdown);
        }

        if ($promo->expires_at !== null && $promo->expires_at->isPast()) {
            $breakdown->promoError = 'This code has expired.';

            return $next($breakdown);
        }

        if ($promo->max_uses !== null && $promo->uses_count >= $promo->max_uses) {
            $breakdown->promoError = 'This code has reached its usage limit.';

            return $next($breakdown);
        }

        if ($promo->min_booking_amount !== null && $breakdown->subtotal < (float) $promo->min_booking_amount) {
            $breakdown->promoError = 'This code requires a minimum booking amount.';

            return $next($breakdown);
        }

        $discount = match ($promo->type) {
            'percentage' => round($breakdown->subtotal * (float) $promo->value / 100, 2),
            'fixed' => min((float) $promo->value, $breakdown->subtotal),
            default => 0.0,
        };

        $breakdown->subtotal = round($breakdown->subtotal - $discount, 2);
        $breakdown->promoDiscount = $discount;
        $breakdown->promoApplied = true;

        return $next($breakdown);
    }
}
