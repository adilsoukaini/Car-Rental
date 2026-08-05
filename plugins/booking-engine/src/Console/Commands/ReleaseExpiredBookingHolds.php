<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Console\Commands;

use App\Core\Support\PaymentGatewayRegistry;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Console\Command;

/**
 * Releases pending bookings whose availability hold has expired — the
 * cleanup half of CoreAvailabilityCheckPipe's 2026-08-04 "pending blocks"
 * revision. Without this, an abandoned checkout (customer closes the tab
 * mid-payment) would lock the vehicle for those dates forever, since a
 * pending hold now genuinely blocks other bookings.
 *
 * This project's first scheduled task — see bootstrap/app.php's
 * withSchedule() and CLAUDE.md's "deposit-gate" section for the
 * verification this got as a genuinely new mechanism, not just "the code
 * compiles."
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

        foreach ($expired as $booking) {
            $this->releaseHold($booking);
        }

        $this->info("Released {$expired->count()} expired booking hold(s).");

        return self::SUCCESS;
    }

    private function releaseHold(Booking $booking): void
    {
        $authorization = Payment::where('booking_id', $booking->id)
            ->where('type', 'deposit_authorization')
            ->whereIn('status', ['pending', 'authorized'])
            ->latest('id')
            ->first();

        if ($authorization !== null) {
            $gateway = PaymentGatewayRegistry::get($authorization->gateway);

            $gateway?->releaseDeposit($authorization);
        }

        $booking->update([
            'status' => 'expired',
            'hold_expires_at' => null,
        ]);
    }
}
