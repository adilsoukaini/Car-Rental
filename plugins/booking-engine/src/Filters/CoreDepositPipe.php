<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Filters;

use Closure;
use Plugins\BookingEngine\Support\PriceBreakdown;

/**
 * Deposit = a flat percentage of the (already discounted) rental subtotal,
 * from config('booking-engine.deposit_percentage_of_subtotal'). Must run
 * after CoreDurationDiscountPipe has set $breakdown->subtotal.
 */
class CoreDepositPipe
{
    public function handle(PriceBreakdown $breakdown, Closure $next): mixed
    {
        $percentage = (float) config('booking-engine.deposit_percentage_of_subtotal', 0);

        $breakdown->depositAmount = round($breakdown->subtotal * ($percentage / 100), 2);

        return $next($breakdown);
    }
}
