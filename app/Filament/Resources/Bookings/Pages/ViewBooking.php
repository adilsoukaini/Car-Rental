<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Core\Events\BookingCancelled;
use App\Core\Events\VehicleCheckedOut;
use App\Core\Events\VehicleReturned;
use App\Core\Support\CancellationPolicyRequest;
use App\Core\Support\FilterRegistry;
use App\Core\Support\PaymentGatewayRegistry;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

/**
 * The mutations available from the admin panel for a booking: cancel it,
 * check the vehicle out, mark it returned, or release/(partially) capture
 * a previously-authorized security deposit hold.
 *
 * Check Out / Mark Returned (added 2026-08-05) are this project's first
 * real dispatch sites for VehicleCheckedOut/VehicleReturned — before this,
 * both events existed only as definitions, with RelocateVehicleOnReturn's
 * only invocation ever being a manual `tinker` dispatch during Phase 5's
 * own verification. Deliberately no time gate (can't check out before
 * pickup_at, etc.) — every other staff action on this page (Cancel,
 * Release, Capture) is gated on status/data, never on whether a scheduled
 * time has arrived, and real-world pickups/returns are routinely early or
 * late. Both actions also sync `Vehicle.status` (`available` <-> `rented`)
 * — deliberately automatic, not left for staff to separately remember on
 * the Vehicle admin form, since VehicleController's public fleet listing
 * filters purely on that field. The "send to maintenance if damage found"
 * branch on return is deferred until damage-reporting exists; a clean
 * return always goes back to `available`.
 *
 * Release/Capture Deposit are gated on the booking's status now being
 * `returned` — a REAL status check, replacing the `pickup_at->isPast()`
 * interim proxy this file used before this same phase built the lifecycle
 * that makes a real check possible.
 *
 * Cancel Booking resolves the deposit per `booking.cancellationPolicy`
 * (refund percentage by proximity to pickup — see
 * plugins/booking-engine/config/booking-engine.php's cancellation_refund_tiers,
 * explicitly flagged there as placeholder business values) as part of the
 * same action: a full refund calls releaseDeposit(); anything less calls
 * captureDeposit() with the forfeited amount — Stripe's own partial-capture
 * behavior automatically releases the uncaptured remainder in that same
 * call (confirmed against Stripe's docs and a real test-mode API call
 * before relying on it), so no second gateway call is needed.
 *
 * Deliberately manual for damage, not automatic on VehicleReturned: whether
 * to release (clean return) or capture (damage found) requires a human
 * judgment call staff makes after inspecting the vehicle — see
 * docs/03-DOMAIN-REQUIREMENTS.md's note on damage/additional-charge being
 * a staff-approval workflow, not an automatic pipeline step.
 */
class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cancelBookingAction(),
            $this->checkOutAction(),
            $this->markReturnedAction(),
            $this->releaseDepositAction(),
            $this->captureDepositAction(),
        ];
    }

    private function checkOutAction(): Action
    {
        return Action::make('checkOut')
            ->label('Check Out')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Marks the vehicle as checked out to the customer. This cannot be undone.')
            ->visible(fn () => $this->booking()->status === 'confirmed')
            ->action(function () {
                $booking = $this->booking();
                $booking->update(['status' => 'checked_out']);
                $booking->vehicle->update(['status' => 'rented']);

                VehicleCheckedOut::dispatch($booking->fresh());

                Notification::make()->title('Vehicle checked out')->success()->send();
            });
    }

    private function markReturnedAction(): Action
    {
        return Action::make('markReturned')
            ->label('Mark Returned')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Marks the vehicle as returned and frees it for other bookings. This cannot be undone.')
            ->visible(fn () => $this->booking()->status === 'checked_out')
            ->action(function () {
                $booking = $this->booking();
                $booking->update(['status' => 'returned']);
                $booking->vehicle->update(['status' => 'available']);

                VehicleReturned::dispatch($booking->fresh());

                Notification::make()->title('Vehicle marked returned')->success()->send();
            });
    }

    private function cancelBookingAction(): Action
    {
        return Action::make('cancelBooking')
            ->label('Cancel Booking')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn () => $this->cancellationModalDescription())
            ->visible(fn () => $this->booking()->status === 'confirmed')
            ->action(function () {
                $booking = $this->booking();
                $authorization = $this->activeAuthorization();

                $booking->update(['status' => 'cancelled']);

                BookingCancelled::dispatch($booking->fresh());

                if ($authorization === null) {
                    Notification::make()->title('Booking cancelled')->success()->send();

                    return;
                }

                $gateway = PaymentGatewayRegistry::get($authorization->gateway);

                if ($gateway === null) {
                    Notification::make()->title('Booking cancelled, but the deposit could not be resolved')->warning()->send();

                    return;
                }

                $refundPercent = $this->cancellationRefundPercent($booking);
                $forfeitAmount = round((float) $authorization->amount * (100 - $refundPercent) / 100, 2);

                if ($forfeitAmount <= 0.0) {
                    $gateway->releaseDeposit($authorization);
                    Notification::make()->title('Booking cancelled — deposit fully released')->success()->send();
                } else {
                    $gateway->captureDeposit($authorization, min($forfeitAmount, (float) $authorization->amount));
                    Notification::make()->title("Booking cancelled — {$forfeitAmount} forfeited as a cancellation fee")->warning()->send();
                }
            });
    }

    private function cancellationModalDescription(): string
    {
        $authorization = $this->activeAuthorization();

        if ($authorization === null) {
            return 'Cancels the booking and frees the vehicle for other bookings covering the same dates. This cannot be undone.';
        }

        $refundPercent = $this->cancellationRefundPercent($this->booking());
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

    private function booking(): Booking
    {
        $record = $this->getRecord();

        if (! $record instanceof Booking) {
            throw new RuntimeException('Expected a Booking record.');
        }

        return $record;
    }

    private function activeAuthorization(): ?Payment
    {
        $payment = $this->booking()
            ->payments()
            ->where('type', 'deposit_authorization')
            ->where('status', 'authorized')
            ->latest('id')
            ->first();

        return $payment instanceof Payment ? $payment : null;
    }

    private function releaseDepositAction(): Action
    {
        return Action::make('releaseDeposit')
            ->label('Release Deposit')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Releases the held security deposit back to the customer — the clean-return path. This cannot be undone.')
            ->visible(fn () => $this->booking()->status === 'returned' && $this->activeAuthorization() !== null)
            ->action(function () {
                $authorization = $this->activeAuthorization();

                if ($authorization === null) {
                    return;
                }

                $gateway = PaymentGatewayRegistry::get($authorization->gateway);

                if ($gateway === null) {
                    Notification::make()->title('Gateway not available')->danger()->send();

                    return;
                }

                $gateway->releaseDeposit($authorization);

                Notification::make()->title('Deposit released')->success()->send();
            });
    }

    private function captureDepositAction(): Action
    {
        return Action::make('captureDeposit')
            ->label('Capture Deposit (Damage)')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Captures some or all of the held security deposit — use when damage was found at return.')
            ->schema(fn () => [
                TextInput::make('amount')
                    ->label('Amount to capture (MAD)')
                    ->numeric()
                    ->minValue(0.01)
                    ->default(fn () => (float) $this->activeAuthorization()?->amount)
                    ->required(),
            ])
            ->visible(fn () => $this->booking()->status === 'returned' && $this->activeAuthorization() !== null)
            ->action(function (array $data) {
                $authorization = $this->activeAuthorization();

                if ($authorization === null) {
                    return;
                }

                $gateway = PaymentGatewayRegistry::get($authorization->gateway);

                if ($gateway === null) {
                    Notification::make()->title('Gateway not available')->danger()->send();

                    return;
                }

                $gateway->captureDeposit($authorization, (float) $data['amount']);

                Notification::make()->title('Deposit captured')->success()->send();
            });
    }
}
