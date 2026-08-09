<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Core\Events\DamageReported;
use App\Core\Events\VehicleCheckedOut;
use App\Core\Events\VehicleReturned;
use App\Core\Support\BookingCancellationService;
use App\Core\Support\PaymentGatewayRegistry;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\DamageReport;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
 *
 * Report Condition (added 2026-08-05) is an optional follow-up action,
 * visible once a booking is `checked_out` or `returned` — deliberately
 * NOT a required step before Check Out/Mark Returned can complete, to
 * avoid changing those actions' already-verified behavior. Free-text
 * description + photos, matching DamageReported's existing shape exactly
 * — no structured checklist (a genuinely different, bigger data model)
 * was built speculatively. Photos are stored on the `local` (private)
 * disk, same treatment as driver-verification's license uploads — this
 * data supports staff-facing deposit disputes, not public display.
 * Deliberately fires DamageReported with no listener yet: whether a
 * report warrants moving the vehicle to `maintenance` or capturing the
 * deposit stays a separate, manual decision via the existing actions on
 * this same page — this is intentional pure data capture, not another
 * "modeled but never consumed" gap.
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
            $this->reportConditionAction(),
            $this->releaseDepositAction(),
            $this->captureDepositAction(),
        ];
    }

    private function reportConditionAction(): Action
    {
        return Action::make('reportCondition')
            ->label('Report Condition')
            ->color('gray')
            ->visible(fn () => in_array($this->booking()->status, ['checked_out', 'returned'], true))
            ->schema([
                Select::make('stage')
                    ->options(['pickup' => 'Pickup', 'return' => 'Return'])
                    ->required(),
                Textarea::make('description')
                    ->label('Condition / damage description')
                    ->required(),
                FileUpload::make('photos')
                    ->disk('local')
                    ->directory('damage-reports')
                    ->image()
                    ->multiple(),
            ])
            ->action(function (array $data) {
                $booking = $this->booking();

                $report = DamageReport::create([
                    'booking_id' => $booking->id,
                    'stage' => $data['stage'],
                    'description' => $data['description'],
                    'photo_paths' => $data['photos'] ?? [],
                    'reported_by' => auth()->id(),
                ]);

                DamageReported::dispatch($booking, $report->stage, $report->description, $report->photo_paths ?? []);

                Notification::make()->title('Condition report logged')->success()->send();
            });
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
                // Cancellation + deposit resolution lives in the shared
                // BookingCancellationService — the same service the mobile
                // JSON API's Api\BookingController::cancel uses, so the
                // money logic can never drift between the panel and the API.
                $result = app(BookingCancellationService::class)->cancel($this->booking());

                match ($result['depositOutcome']) {
                    'released' => Notification::make()->title('Booking cancelled — deposit fully released')->success()->send(),
                    'captured' => Notification::make()->title("Booking cancelled — {$result['forfeitAmount']} forfeited as a cancellation fee")->warning()->send(),
                    'gateway_unavailable' => Notification::make()->title('Booking cancelled, but the deposit could not be resolved')->warning()->send(),
                    default => Notification::make()->title('Booking cancelled')->success()->send(),
                };
            });
    }

    private function cancellationModalDescription(): string
    {
        return app(BookingCancellationService::class)->cancellationModalDescription($this->booking());
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
