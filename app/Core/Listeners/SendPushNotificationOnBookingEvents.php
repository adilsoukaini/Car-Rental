<?php

declare(strict_types=1);

namespace App\Core\Listeners;

use App\Core\Events\BookingCancelled;
use App\Core\Events\BookingConfirmed;
use App\Core\Events\VehicleCheckedOut;
use App\Core\Events\VehicleReturned;
use App\Models\Notification;
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
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $maxExceptions = 3;

    public function __construct(
        private readonly PushNotificationService $push,
    ) {}

    public function handle(
        BookingConfirmed|BookingCancelled|VehicleCheckedOut|VehicleReturned $event,
    ): void {
        $booking = $event->booking;
        $type = $this->notificationType($event);

        // The union type above is exhaustive — the `default` arm is the
        // VehicleReturned case (last remaining member), never a null fallback,
        // so `$title`/`$body` are always set here.
        $title = match (true) {
            $event instanceof BookingConfirmed => 'Réservation confirmée',
            $event instanceof BookingCancelled => 'Réservation annulée',
            $event instanceof VehicleCheckedOut => 'Véhicule récupéré',
            default => 'Retour effectué',
        };

        $body = match (true) {
            $event instanceof BookingConfirmed => 'Votre réservation #'.$booking->booking_number.' est confirmée',
            $event instanceof BookingCancelled => 'Votre réservation #'.$booking->booking_number.' a été annulée',
            $event instanceof VehicleCheckedOut => 'Vous avez récupéré votre véhicule',
            default => 'Véhicule retourné avec succès',
        };

        // Save to notification history (inbox) — works for both guests and auth users.
        Notification::create([
            'user_id' => $booking->user_id,
            'guest_email' => $booking->guest_email,
            'booking_id' => $booking->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => [
                'bookingId' => $booking->id,
                'bookingNumber' => $booking->booking_number,
                'vehicleName' => $booking->vehicle->make.' '.$booking->vehicle->model,
            ],
        ]);

        // Guest bookings have no account to push to.
        if ($booking->user_id === null) {
            return;
        }

        $this->push->sendToUser($booking->user, 'Car Rental', $body, [
            'type' => $type,
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
