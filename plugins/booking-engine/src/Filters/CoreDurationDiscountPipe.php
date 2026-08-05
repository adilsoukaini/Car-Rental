<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Filters;

use App\Models\Vehicle;
use Closure;
use Plugins\BookingEngine\Support\PriceBreakdown;

/**
 * Computes days (partial days round UP to the next full day — a 2 day
 * 3 hour rental bills as 3 days, minimum 1 day), applies the cliff/
 * threshold duration-discount tier from config('booking-engine.duration_discount_tiers')
 * (the highest threshold met wins; discounts are not cumulative/graduated),
 * and sets the discounted rental subtotal.
 */
class CoreDurationDiscountPipe
{
    public function handle(PriceBreakdown $breakdown, Closure $next): mixed
    {
        $vehicle = Vehicle::findOrFail($breakdown->request->vehicleId);

        $seconds = $breakdown->request->pickupAt->diffInSeconds($breakdown->request->returnAt);
        $days = max(1, (int) ceil($seconds / 86400));

        $discountPercent = 0;
        $tiers = config('booking-engine.duration_discount_tiers', []);
        krsort($tiers);

        foreach ($tiers as $thresholdDays => $percent) {
            if ($days >= $thresholdDays) {
                $discountPercent = (int) $percent;
                break;
            }
        }

        $dailyRate = (float) $vehicle->daily_rate;
        $subtotal = round($dailyRate * $days * (1 - $discountPercent / 100), 2);

        $breakdown->dailyRate = $dailyRate;
        $breakdown->days = $days;
        $breakdown->discountPercent = $discountPercent;
        $breakdown->subtotal = $subtotal;

        return $next($breakdown);
    }
}
