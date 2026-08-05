<?php

declare(strict_types=1);

namespace Plugins\Reviews\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Rental-domain equivalent of the source e-commerce project's
 * VerifiedPurchaseChecker — deliberately NOT a direct port. That checker
 * requires only Order.payment_status === 'paid' (payment succeeded,
 * delivery not required); a car-rental review is about the actual rental
 * experience, which can only genuinely be assessed once the rental has
 * concluded. Gated on a real `returned` booking specifically — not
 * `confirmed`/`checked_out` — made possible (not just copied) by the
 * checkout/return lifecycle phase that made `returned` a real, reachable
 * status for the first time.
 */
class VerifiedRentalChecker
{
    public function check(User $user, Vehicle $vehicle): bool
    {
        return Booking::where('user_id', $user->id)
            ->where('vehicle_id', $vehicle->id)
            ->where('status', 'returned')
            ->exists();
    }
}
