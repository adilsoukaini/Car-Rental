<?php

declare(strict_types=1);

namespace App\Core\Listeners;

use App\Core\Events\VehicleCheckedOut;
use App\Mail\BookingCheckedOut;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Queues the "rental has started" email when a vehicle is checked out.
 * Same shape as SendBookingConfirmationEmail: the recipient is the guest
 * email if the booking is a guest booking, otherwise the user's email, and
 * the listener no-ops if neither can be resolved.
 */
class SendBookingCheckedOutEmail implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $maxExceptions = 3;
    public function handle(VehicleCheckedOut $event): void
    {
        $booking = $event->booking->loadMissing(['vehicle', 'pickupLocation', 'returnLocation', 'user']);

        $recipient = $booking->guest_email ?? $booking->user?->email;

        if (! $recipient) {
            return;
        }

        // Guests get a fresh 48-hour signed URL; authenticated users get a plain route.
        $trackUrl = $booking->user_id === null
            ? URL::temporarySignedRoute(
                'bookings.show',
                now()->addHours(48),
                ['booking' => $booking->id],
            )
            : route('bookings.show', ['booking' => $booking->id]);

        Mail::to($recipient)
            ->send((new BookingCheckedOut($booking))->with(['trackUrl' => $trackUrl]));
    }
}
