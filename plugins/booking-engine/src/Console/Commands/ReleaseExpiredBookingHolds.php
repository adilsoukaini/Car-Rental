<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Console\Commands;

use App\Core\Support\PaymentGatewayRegistry;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Releases pending bookings whose availability hold has expired — the
 * cleanup half of CoreAvailabilityCheckPipe's 2026-08-04 "pending blocks"
 * revision. Without this, an abandoned checkout (customer closes the tab
 * mid-payment) would lock the vehicle for those dates forever, since a
 * pending hold now genuinely blocks other bookings.
 *
 * Race-safe (2026-08-11): each booking is locked and its status re-checked
 * atomically before transitioning to expired, preventing the cron from
 * corrupting a booking that was confirmed concurrently. Per-booking
 * try/catch prevents one Stripe failure from aborting the whole run.
 */
class ReleaseExpiredBookingHolds extends Command
{
    protected $signature = 'bookings:release-expired-holds';

    protected $description = 'Release pending bookings whose availability hold has expired, and cancel any associated deposit-authorization hold.';

    public function handle(): int
    {
        $expired = Booking::query()
            ->where('status', 'pending')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->get();

        $released = 0;

        foreach ($expired as $booking) {
            try {
                if ($this->releaseHold($booking)) {
                    $released++;
                }
            } catch (\Throwable $e) {
                // One Stripe failure must not block the rest of the run.
                Log::warning('Failed to release expired booking hold', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Released {$released} of {$expired->count()} expired booking hold(s).");

        return self::SUCCESS;
    }

    /**
     * Returns true if the hold was actually released, false if it was skipped
     * because the booking was already confirmed (concurrent confirm race).
     */
    private function releaseHold(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            // Lock the booking row and re-check status. A booking may have
            // been confirmed between our initial SELECT and this lock acquire.
            $fresh = Booking::where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->status !== 'pending') {
                // Already confirmed or cancelled — do not touch.
                return false;
            }

            $authorization = Payment::where('booking_id', $fresh->id)
                ->where('type', 'deposit_authorization')
                ->whereIn('status', ['pending', 'authorized'])
                ->latest('id')
                ->first();

            if ($authorization !== null) {
                $gateway = PaymentGatewayRegistry::get($authorization->gateway);
                $gateway?->releaseDeposit($authorization);
            }

            $fresh->update([
                'status' => 'expired',
                'hold_expires_at' => null,
            ]);

            return true;
        });
    }
}
