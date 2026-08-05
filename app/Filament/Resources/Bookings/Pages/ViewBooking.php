<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Core\Events\BookingCancelled;
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
 * The mutations available from the admin panel for a booking: cancel it, or
 * release/(partially) capture a previously-authorized security deposit
 * hold. This is the ONLY place in the application that actually calls
 * PaymentGateway::releaseDeposit()/captureDeposit() outside of tests —
 * without it those methods have no real caller in production at all. As of
 * 2026-08-04, that's genuinely by construction, not oversight:
 * authorizeDeposit() itself is never called anywhere in the real booking
 * flow (deposit-gated confirmation is a real, undesigned future decision —
 * see CLAUDE.md's booking-confirmation-email section), so
 * releaseDeposit()/captureDeposit()'s visibility gate (an `authorized`
 * deposit_authorization Payment row) can never actually be satisfied by a
 * real booking today. Both buttons are correctly implemented and
 * permanently invisible until that gap is closed — not a bug in this file.
 *
 * Cancel is status-only (sets status = 'cancelled', dispatches
 * BookingCancelled) — no refund logic. `booking.cancellationPolicy` (the
 * refund-percentage-by-proximity-to-pickup filter from
 * docs/03-DOMAIN-REQUIREMENTS.md) is deliberately not built yet, for the
 * same reason: there's no real captured deposit to compute a refund
 * against until the deposit-gate decision above is made.
 *
 * Deliberately manual, not automatic on VehicleReturned: whether to
 * release (clean return) or capture (damage found) requires a human
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
            $this->releaseDepositAction(),
            $this->captureDepositAction(),
        ];
    }

    private function cancelBookingAction(): Action
    {
        return Action::make('cancelBooking')
            ->label('Cancel Booking')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Cancels the booking and frees the vehicle for other bookings covering the same dates. This cannot be undone.')
            ->visible(fn () => $this->booking()->status === 'confirmed')
            ->action(function () {
                $booking = $this->booking();
                $booking->update(['status' => 'cancelled']);

                BookingCancelled::dispatch($booking->fresh());

                Notification::make()->title('Booking cancelled')->success()->send();
            });
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
            ->visible(fn () => $this->activeAuthorization() !== null)
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
            ->visible(fn () => $this->activeAuthorization() !== null)
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
