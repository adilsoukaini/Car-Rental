<?php

declare(strict_types=1);

namespace App\Core\Listeners;

use App\Core\Events\BookingConfirmed;
use App\Mail\BookingConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendBookingConfirmationEmail implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $maxExceptions = 3;

    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking->loadMissing(['vehicle', 'pickupLocation', 'returnLocation', 'user']);

        $recipient = $booking->guest_email ?? $booking->user?->email;

        if (! $recipient) {
            return;
        }

        // Guests get a fresh 48-hour signed URL; authenticated users get a plain route.
        if ($booking->user_id === null) {
            $confirmationUrl = URL::temporarySignedRoute(
                'bookings.show',
                now()->addHours(48),
                ['booking' => $booking->id],
            );
        } else {
            $confirmationUrl = route('bookings.show', ['booking' => $booking->id]);
        }

        Mail::to($recipient)
            ->send((new BookingConfirmation($booking))->with(['confirmationUrl' => $confirmationUrl]));
    }
}
