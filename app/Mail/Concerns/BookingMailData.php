<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

/**
 * The view data every booking-status email renders: the vehicle, the
 * pickup/return schedule, the financials, and the booking reference.
 * Extracted into one place so all four booking mailables stay in sync — a
 * field added here appears in every template that consumes it, instead of
 * drifting across four near-identical content() methods.
 */
trait BookingMailData
{
    /**
     * @return array{
     *     vehicle: Vehicle,
     *     pickupAt: Carbon,
     *     returnAt: Carbon,
     *     total: float,
     *     deposit: float,
     *     bookingNumber: string,
     *     pickupLocation: Location,
     *     returnLocation: Location,
     * }
     */
    protected function bookingMailData(Booking $booking): array
    {
        return [
            'vehicle' => $booking->vehicle,
            'pickupAt' => $booking->pickup_at,
            'returnAt' => $booking->return_at,
            'total' => $booking->total_price,
            'deposit' => $booking->security_deposit_amount,
            'bookingNumber' => $booking->booking_number,
            'pickupLocation' => $booking->pickupLocation,
            'returnLocation' => $booking->returnLocation,
        ];
    }
}
