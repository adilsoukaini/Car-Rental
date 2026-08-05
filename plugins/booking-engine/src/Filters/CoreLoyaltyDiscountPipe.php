<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Filters;

use App\Models\Booking;
use Closure;
use Plugins\BookingEngine\Support\PriceBreakdown;

/**
 * Applies the cliff/threshold loyalty discount tier from
 * config('booking-engine.loyalty_discount_tiers'), keyed on the customer's
 * count of prior RETURNED bookings — a "repeat customer" is someone who has
 * actually completed prior rentals, not one who currently has a booking in
 * flight (same reasoning as the reviews plugin's VerifiedRentalChecker).
 * Guests (`userId === null`) have no persistent identity across bookings
 * and are always exempt, same precedent as driver verification.
 *
 * Must run after CoreDurationDiscountPipe (needs $breakdown->dailyRate/days
 * already set) — registered at a lower priority in
 * BookingEngineServiceProvider. If the loyalty tier's percent is higher
 * than the duration discount already applied, it REPLACES it rather than
 * stacking: the two never combine, so the maximum discount on any booking
 * is always exactly one tier that was actually defined and reasoned about.
 */
class CoreLoyaltyDiscountPipe
{
    public function handle(PriceBreakdown $breakdown, Closure $next): mixed
    {
        $userId = $breakdown->request->userId;
        $loyaltyPercent = 0;

        if ($userId !== null) {
            $priorReturnedRentals = Booking::where('user_id', $userId)
                ->where('status', 'returned')
                ->count();

            $tiers = config('booking-engine.loyalty_discount_tiers', []);
            krsort($tiers);

            foreach ($tiers as $thresholdRentals => $percent) {
                if ($priorReturnedRentals >= $thresholdRentals) {
                    $loyaltyPercent = (int) $percent;
                    break;
                }
            }
        }

        if ($loyaltyPercent > $breakdown->discountPercent) {
            $baseAmount = $breakdown->dailyRate * $breakdown->days;
            $breakdown->discountPercent = $loyaltyPercent;
            $breakdown->subtotal = round($baseAmount * (1 - $loyaltyPercent / 100), 2);
        }

        return $next($breakdown);
    }
}
