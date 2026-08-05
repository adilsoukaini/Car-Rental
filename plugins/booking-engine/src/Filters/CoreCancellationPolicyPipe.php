<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Filters;

use Closure;
use Plugins\BookingEngine\Support\CancellationPolicyRequest;

/**
 * Cliff/threshold refund-percentage-by-proximity-to-pickup, same model as
 * CoreDurationDiscountPipe's discount tiers — the highest day-count
 * threshold met wins, not cumulative. Config values
 * (`booking-engine.cancellation_refund_tiers`) are explicitly flagged as
 * placeholder business numbers, not confirmed policy — see the config
 * file's docblock.
 *
 * Days-until-pickup is computed as a whole-day floor (partial days round
 * DOWN here, the opposite of CoreDurationDiscountPipe's round-up for
 * rental duration) — a cancellation 6 days and 23 hours before pickup gets
 * the "6 days" tier, not "7 days". This is the more conservative direction
 * for a refund calculation: rounding in the business's favor, not the
 * customer's, on a boundary that's ambiguous either way.
 */
class CoreCancellationPolicyPipe
{
    public function handle(CancellationPolicyRequest $request, Closure $next): mixed
    {
        // Computed directly from timestamps, not Carbon::diffInDays() —
        // that method's signed-difference sign convention has flipped
        // across Carbon versions historically and is easy to get backwards
        // silently. This is unambiguous: positive = days remaining until
        // pickup, negative = pickup has already passed.
        $seconds = $request->pickupAt->getTimestamp() - $request->cancelledAt->getTimestamp();
        $daysUntilPickup = (int) floor($seconds / 86400);

        $refundPercent = 0;
        $tiers = config('booking-engine.cancellation_refund_tiers', []);
        krsort($tiers);

        foreach ($tiers as $thresholdDays => $percent) {
            if ($daysUntilPickup >= $thresholdDays) {
                $refundPercent = (int) $percent;
                break;
            }
        }

        $request->refundPercent = $refundPercent;

        return $next($request);
    }
}
