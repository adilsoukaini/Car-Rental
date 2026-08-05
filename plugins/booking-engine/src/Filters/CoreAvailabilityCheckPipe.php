<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Filters;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Vehicle;
use Closure;
use Plugins\BookingEngine\Support\AvailabilityCheckRequest;

/**
 * The base availability check: no date-overlapping confirmed/checked_out/
 * still-held-pending booking on the same vehicle, the vehicle is currently
 * at the requested pickup location, and that location is active (a
 * location's `is_active` flag is a soft-disable for NEW bookings only — it
 * does not retroactively invalidate existing bookings that already
 * reference it, same precedent as a Vehicle's `status` field).
 *
 * Boundary handling is exclusive-end / half-open: a booking ending exactly
 * when another starts on the same vehicle does NOT count as overlapping —
 * no turnaround buffer is enforced here by design (see
 * docs/03-DOMAIN-REQUIREMENTS.md's explicit warning: a buffer is expected
 * to be added as a separate, later pipe on this same filter before this
 * platform accepts real production bookings — it is deliberately NOT built
 * into this base primitive).
 *
 * **`pending` blocking is a deliberate 2026-08-04 revision of Phase 5's
 * original decision, not an oversight.** Phase 5 shipped "only confirmed/
 * checked_out block" because at the time, pending→confirmed happened in
 * one atomic synchronous call with no real time gap — the confirm-time
 * lock in BookingCreator was the actual safety mechanism, and the question
 * of "should an in-progress booking reserve the vehicle" never arose.
 * Phase B introduced the first real gap in that transition (a genuine
 * window for the customer to enter card details and possibly complete 3D
 * Secure), and the moment that gap exists, non-blocking pending stops
 * being a neutral default and becomes an actual race: two customers could
 * both pass this check, both get a real Stripe hold placed on their card,
 * and only one could ever reach `confirmed` — leaving the loser with money
 * held for a car they can never get. A `pending` booking now blocks ONLY
 * while its hold is still live (`hold_expires_at` in the future) —
 * `ReleaseExpiredBookingHolds` (this project's first scheduled task)
 * expires abandoned holds, at which point they stop blocking again. A
 * `pending` row with `hold_expires_at === null` (from the older, still-
 * supported `BookingCreator::create()` immediate-confirm path, which never
 * actually persists a `pending` row — it goes straight to `confirmed`) is
 * excluded, since a null expiry has no defined "is this hold still live"
 * answer and this pipe should never guess.
 */
class CoreAvailabilityCheckPipe
{
    private const BLOCKING_STATUSES = ['confirmed', 'checked_out'];

    public function handle(AvailabilityCheckRequest $request, Closure $next): mixed
    {
        $vehicle = Vehicle::find($request->vehicleId);

        if ($vehicle === null || $vehicle->location_id !== $request->pickupLocationId) {
            return false;
        }

        $pickupLocation = Location::find($request->pickupLocationId);

        if ($pickupLocation === null || ! $pickupLocation->is_active) {
            return false;
        }

        $overlaps = Booking::query()
            ->where('vehicle_id', $request->vehicleId)
            ->where(function ($query) {
                $query->whereIn('status', self::BLOCKING_STATUSES)
                    ->orWhere(function ($query) {
                        $query->where('status', 'pending')
                            ->whereNotNull('hold_expires_at')
                            ->where('hold_expires_at', '>', now());
                    });
            })
            ->when(
                $request->excludingBookingId !== null,
                fn ($query) => $query->whereKeyNot($request->excludingBookingId),
            )
            ->where('pickup_at', '<', $request->returnAt)
            ->where('return_at', '>', $request->pickupAt)
            ->exists();

        if ($overlaps) {
            return false;
        }

        return $next($request);
    }
}
