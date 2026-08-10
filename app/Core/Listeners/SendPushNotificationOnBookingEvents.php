<?php

declare(strict_types=1);

namespace App\Core\Listeners;

use App\Core\Events\BookingCancelled;
use App\Core\Events\BookingConfirmed;
use App\Core\Events\VehicleCheckedOut;
use App\Core\Events\VehicleReturned;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends a push notification to the customer's devices on booking lifecycle
 * events. Registered for four events in AppServiceProvider::boot() (one
 * listener class, one queued handle() per event — the union type keeps it to
 * a single method).
 *
 * Messages are French to match the storefront's default locale (the mobile
 * app's `useTranslation()` defaults to 'fr').
 *
 * Only the booking's registered user is notified — guest bookings
 * (user_id = null) have no device tokens and are covered by the confirmation
 * email alone.
 *
 * Queued (ShouldQueue), matching the email listeners: the Expo HTTP call must
 * not block the request that fired the event (the checkout confirmation).
 */
class SendPushNotificationOnBookingEvents implements ShouldQueue
{
    public function __construct(
        private readonly PushNotificationService $push,
    ) {}

    public function handle(
        BookingConfirmed|BookingCancelled|VehicleCheckedOut|VehicleReturned $event,
    ): void {
        $booking = $event->booking;

        // Guest bookings have no account to push to.
        if ($booking->user_id === null) {
            return;
        }

        $body = match (true) {
            $event instanceof BookingConfirmed => 'Votre réservation #'.$booking->id.' est confirmée',
            $event instanceof BookingCancelled => 'Votre réservation #'.$booking->id.' a été annulée',
            $event instanceof VehicleCheckedOut => 'Vous avez récupéré votre véhicule',
            $event instanceof VehicleReturned => 'Véhicule retourné avec succès',
            default => null,
        };

        if ($body === null) {
            return;
        }

        $this->push->sendToUser($booking->user, 'Car Rental', $body, [
            'type' => $this->notificationType($event),
            'bookingId' => $booking->id,
        ]);
    }

    private function notificationType(
        BookingConfirmed|BookingCancelled|VehicleCheckedOut|VehicleReturned $event,
    ): string {
        return match (true) {
            $event instanceof BookingConfirmed => 'booking_confirmed',
            $event instanceof BookingCancelled => 'booking_cancelled',
            $event instanceof VehicleCheckedOut => 'vehicle_checked_out',
            $event instanceof VehicleReturned => 'vehicle_returned',
        };
    }
}
