<?php

declare(strict_types=1);

namespace Plugins\Promotions\Listeners;

use App\Core\Events\BookingConfirmed;
use Plugins\Promotions\Models\PromoCode;

/**
 * The single place a promo code's uses_count is incremented — when a booking
 * that genuinely applied the code is CONFIRMED (BookingConfirmed), matching
 * the e-commerce project's "increment at order placement, not at quote"
 * pattern. BookingCreator stores the applied code in `Booking.metadata['promo_code']`
 * (only when PromoCodePipe actually applied it), so this listener simply
 * reads the confirmed booking's metadata and bumps the matching row.
 *
 * Live in the promotions plugin, listening to the core BookingConfirmed
 * event — promotions never imports booking-engine's BookingCreator, and
 * booking-engine never references promotions (Hard Rule 2).
 */
class IncrementPromoCodeUsage
{
    public function handle(BookingConfirmed $event): void
    {
        $code = $event->booking->metadata['promo_code'] ?? null;

        if ($code === null || trim((string) $code) === '') {
            return;
        }

        PromoCode::query()
            ->whereRaw('LOWER(code) = ?', [strtolower(trim((string) $code))])
            ->increment('uses_count');
    }
}
