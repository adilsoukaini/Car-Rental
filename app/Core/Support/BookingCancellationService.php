<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The booking-cancellation business logic — the single place a booking's
 * status is flipped to `cancelled` and its deposit hold is resolved per the
 * `booking.cancellationPolicy` pipeline. Extracted from the Filament
 * ViewBooking action (2026-08-09) so the admin panel and the mobile JSON API
 * (App\Http\Controllers\Api\BookingController::cancel) share one
 * implementation — deposit/money logic must never drift between callers.
 *
 * Resolution semantics (unchanged from ViewBooking):
 *   - a booking is cancellable only while `confirmed`;
 *   - the refund percentage comes from `booking.cancellationPolicy`
 *     (defaults to 100% when no pipe is registered — cancelling forfeits
 *     nothing);
 *   - 100% refund → release the whole hold; less → partially capture the
 *     forfeited amount (Stripe's partial capture auto-releases the uncaptured
 *     remainder in the same call, so no second gateway call is needed).
 */
class BookingCancellationService
{
    /**
     * Cancel a confirmed booking, dispatch BookingCancelled, and resolve the
     * deposit hold.
     *
     * @return array{
     *     status: string,
     *     refundPercent: int,
     *     forfeitAmount: float,
     *     depositResolved: bool,
     *     depositOutcome: string|null,
     * } depositOutcome is one of null (no hold to resolve),
     *   'released' | 'captured' | 'gateway_unavailable'.
     *
     * @throws ValidationException when the booking is not cancellable.
     */
    public function cancel(Booking $booking): array
    {
        return DB::transaction(function () use ($booking) {
            // Lock the booking row to prevent concurrent cancellations from
            // double-releasing the deposit. Re-read status under the lock.
            $fresh = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'booking' => 'Only confirmed bookings can be cancelled.',
                ]);
            }

            $authorization = $this->activeAuthorization($fresh);

            $fresh->update(['status' => 'cancelled']);
            $fresh->refresh();

            BookingCancelled::dispatch($fresh);

            if ($authorization === null) {
                return [
                    'status' => $fresh->status,
                    'refundPercent' => 100,
                    'forfeitAmount' => 0.0,
                    'depositResolved' => false,
                    'depositOutcome' => null,
                ];
            }

            $gateway = PaymentGatewayRegistry::get($authorization->gateway);

            if ($gateway === null) {
                return [
                    'status' => $fresh->status,
                    'refundPercent' => 100,
                    'forfeitAmount' => 0.0,
                    'depositResolved' => false,
                    'depositOutcome' => 'gateway_unavailable',
                ];
            }

            $refundPercent = $this->cancellationRefundPercent($fresh);
            $forfeitAmount = round((float) $authorization->amount * (100 - $refundPercent) / 100, 2);

            if ($forfeitAmount <= 0.0) {
                $gateway->releaseDeposit($authorization);

                return [
                    'status' => $fresh->status,
                    'refundPercent' => $refundPercent,
                    'forfeitAmount' => 0.0,
                    'depositResolved' => true,
                    'depositOutcome' => 'released',
                ];
            }

            $gateway->captureDeposit($authorization, min($forfeitAmount, (float) $authorization->amount));

            return [
                'status' => $fresh->status,
                'refundPercent' => $refundPercent,
                'forfeitAmount' => $forfeitAmount,
                'depositResolved' => true,
                'depositOutcome' => 'captured',
            ];
        });
    }

    /**
     * The confirmation-modal copy for a pending cancellation — shows the
     * computed refund/forfeit amounts before staff confirms.
     */
    public function cancellationModalDescription(Booking $booking): string
    {
        $authorization = $this->activeAuthorization($booking);

        if ($authorization === null) {
            return 'Cancels the booking and frees the vehicle for other bookings covering the same dates. This cannot be undone.';
        }

        $refundPercent = $this->cancellationRefundPercent($booking);
        $forfeitAmount = round((float) $authorization->amount * (100 - $refundPercent) / 100, 2);

        return "Cancels the booking and frees the vehicle for other bookings covering the same dates. Based on the cancellation policy, {$refundPercent}% of the held deposit will be refunded ({$forfeitAmount} forfeited as a cancellation fee). This cannot be undone.";
    }

    private function cancellationRefundPercent(Booking $booking): int
    {
        $request = new CancellationPolicyRequest(
            bookingId: $booking->id,
            pickupAt: $booking->pickup_at,
            cancelledAt: now(),
        );

        /** @var CancellationPolicyRequest $result */
        $result = FilterRegistry::apply('booking.cancellationPolicy', $request);

        return $result->refundPercent;
    }

    private function activeAuthorization(Booking $booking): ?Payment
    {
        $payment = $booking->payments()
            ->where('type', 'deposit_authorization')
            ->where('status', 'authorized')
            ->latest('id')
            ->first();

        return $payment instanceof Payment ? $payment : null;
    }
}
