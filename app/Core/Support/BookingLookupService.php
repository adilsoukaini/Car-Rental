<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Models\Booking;

/**
 * Resolves a booking from the public booking_number + contact-email pair used
 * by the track-booking flow. Shared between the web BookingController::lookup
 * (which redirects to the detail page) and the mobile JSON API
 * (Api\BookingController::lookup, which returns the booking directly) so the
 * "what counts as a successful lookup" rule never drifts.
 *
 * The pair acts as the credential: booking_number is high-entropy (10 random
 * uppercase chars) and the email must match the address used at booking time.
 */
class BookingLookupService
{
    public function resolve(string $bookingNumber, string $email): ?Booking
    {
        return Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where(function ($query) use ($email) {
                $query->where('guest_email', $email)
                    ->orWhereHas('user', fn ($q) => $q->where('email', $email));
            })
            ->first();
    }
}
