<?php

declare(strict_types=1);

namespace App\Core\Listeners;

use App\Core\Events\BookingCancelled;
use App\Core\Support\CancellationPolicyRequest;
use App\Core\Support\FilterRegistry;
use App\Mail\BookingCancelled as BookingCancelledMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the "booking cancelled" email. Same recipient-resolution shape as
 * SendBookingConfirmationEmail, plus the cancellation-policy refund
 * information: the email shows how much of a held security deposit is
 * refunded (by proximity to pickup, per `booking.cancellationPolicy`) — but
 * only when a deposit was actually held for this booking.
 */
class SendBookingCancelledEmail implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $maxExceptions = 3;

    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking->loadMissing(['vehicle', 'pickupLocation', 'returnLocation', 'user']);

        $recipient = $booking->guest_email ?? $booking->user?->email;

        if (! $recipient) {
            return;
        }

        // A deposit that was ever held shows up as a deposit_authorization
        // that is either still 'authorized' or has since been resolved
        // ('succeeded' — release or capture both land here). This runs as a
        // queued job, so it's the post-resolution state we actually see.
        $deposit = $booking->payments()
            ->where('type', 'deposit_authorization')
            ->whereIn('status', ['authorized', 'succeeded'])
            ->latest('id')
            ->first();

        $hasDeposit = $deposit !== null;

        $refundPercent = 100;

        if ($hasDeposit) {
            $request = new CancellationPolicyRequest(
                bookingId: $booking->id,
                pickupAt: $booking->pickup_at,
                cancelledAt: now(),
            );

            /** @var CancellationPolicyRequest $result */
            $result = FilterRegistry::apply('booking.cancellationPolicy', $request);
            $refundPercent = $result->refundPercent;
        }

        Mail::to($recipient)
            ->send((new BookingCancelledMailable($booking))->with([
                'hasDeposit' => $hasDeposit,
                'refundPercent' => $refundPercent,
            ]));
    }
}
