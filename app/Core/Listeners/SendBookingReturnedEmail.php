<?php

declare(strict_types=1);

namespace App\Core\Listeners;

use App\Core\Events\VehicleReturned;
use App\Mail\BookingReturned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/**
 * Queues the "rental is complete" email when a vehicle is returned. Same
 * recipient-resolution shape as SendBookingConfirmationEmail.
 */
class SendBookingReturnedEmail implements ShouldQueue
{
    public function handle(VehicleReturned $event): void
    {
        $booking = $event->booking->loadMissing(['vehicle', 'pickupLocation', 'returnLocation', 'user']);

        $recipient = $booking->guest_email ?? $booking->user?->email;

        if (! $recipient) {
            return;
        }

        // The vehicle detail page is where a returned customer leaves a
        // review. It's a fleet-management (plugin) route, so guard it — a
        // core listener must not throw if that plugin is ever disabled.
        $reviewUrl = Route::has('vehicles.show')
            ? route('vehicles.show', ['vehicle' => $booking->vehicle_id])
            : null;

        Mail::to($recipient)
            ->send((new BookingReturned($booking))->with(['reviewUrl' => $reviewUrl]));
    }
}
