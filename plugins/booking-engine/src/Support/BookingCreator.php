<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Support;

use App\Core\Events\BookingConfirmed;
use App\Core\Support\DriverEligibilityCheckRequest;
use App\Core\Support\FilterRegistry;
use App\Models\Booking;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Plugins\BookingEngine\Exceptions\DriverNotEligibleException;
use Plugins\BookingEngine\Exceptions\VehicleNotAvailableException;

/**
 * The single entry point for creating a booking, confirmed or pending.
 *
 * Guards against the TOCTOU race where two concurrent requests both pass
 * the availability check before either has inserted its booking row: the
 * target vehicle row is locked (`lockForUpdate()`) for the duration of the
 * transaction, so a second concurrent call attempting the same lock blocks
 * until the first commits — at which point its own availability recheck
 * correctly sees the first call's newly-inserted booking and fails.
 *
 * This guarantee depends on every confirmed-booking creation going through
 * this single entry point — a booking inserted directly via Booking::create()
 * elsewhere would bypass both the lock and the availability check entirely.
 *
 * Known limitation: SQLite (this project's dev/test database) has no true
 * row-level locking — it serializes writes at the whole-database level
 * instead. The lock call is still correct and portable (Laravel abstracts
 * it), but genuine concurrent-connection race protection can only be
 * meaningfully proven against a database with real row-level locks
 * (MySQL/Postgres). See docs/event-registry.md and CLAUDE.md's Phase 5
 * section for the honest test-coverage boundary this creates.
 *
 * `total_price` and `security_deposit_amount` are computed here via
 * `booking.priceCalculation`, not accepted from the caller — a raw
 * caller-supplied price would bypass the pricing engine entirely, which
 * is not acceptable for financial data (CLAUDE.md rule 5).
 *
 * Driver eligibility (`booking.driverEligibilityCheck`) is only checked
 * for a registered user (`$attributes['user_id']` present) — guest
 * bookings are exempt in this phase. See CLAUDE.md's driver-verification
 * section for the full reasoning (enforcing this for guests would either
 * force account creation or require a pending-then-admin-review workflow
 * that doesn't exist yet).
 *
 * `create()` (immediate confirm, no payment gate) still exists unchanged
 * for callers that genuinely don't need one (tests, tinker, any future
 * admin-initiated booking). The real public checkout flow
 * (`BookingCheckoutController`) uses `createPending()` +
 * `confirmPending()` instead, added 2026-08-04 for the real deposit-hold
 * gate — see CLAUDE.md's "deposit-gate" section for the full design.
 */
class BookingCreator
{
    /**
     * @param  array<string, mixed>  $attributes  Booking::create()-compatible attributes,
     *                                            minus `status`, `total_price`, and
     *                                            `security_deposit_amount` (all computed here).
     *
     * @throws VehicleNotAvailableException
     * @throws DriverNotEligibleException
     */
    public function create(array $attributes): Booking
    {
        return DB::transaction(function () use ($attributes): Booking {
            [, $breakdown] = $this->validateAndPrice($attributes);

            $booking = Booking::create([
                ...$attributes,
                'status' => 'confirmed',
                'total_price' => $breakdown->totalPrice(),
                'security_deposit_amount' => $breakdown->depositAmount,
            ]);

            BookingConfirmed::dispatch($booking);

            return $booking;
        });
    }

    /**
     * Creates a `pending` booking with a time-limited availability hold
     * (`hold_expires_at`, `config('booking-engine.hold_ttl_minutes')`) —
     * the vehicle is reserved for the customer to complete payment, per
     * CoreAvailabilityCheckPipe's 2026-08-04 revision. Does NOT dispatch
     * BookingConfirmed — the booking isn't confirmed yet. Callers are
     * expected to authorize a deposit hold against the returned booking,
     * then call confirmPending() once that hold is genuinely in place.
     *
     * @param  array<string, mixed>  $attributes  Same shape as create().
     *
     * @throws VehicleNotAvailableException
     * @throws DriverNotEligibleException
     */
    public function createPending(array $attributes): Booking
    {
        return DB::transaction(function () use ($attributes): Booking {
            [, $breakdown] = $this->validateAndPrice($attributes);

            return Booking::create([
                ...$attributes,
                'status' => 'pending',
                'hold_expires_at' => now()->addMinutes((int) config('booking-engine.hold_ttl_minutes', 15)),
                'total_price' => $breakdown->totalPrice(),
                'security_deposit_amount' => $breakdown->depositAmount,
            ]);
        });
    }

    /**
     * Transitions a pending booking (with a live hold) to confirmed, after
     * its deposit hold has genuinely been authorized. Re-runs the
     * availability check (excluding the booking's own pending row) as
     * defense-in-depth — by construction, nothing else could have taken
     * the same vehicle/dates while this hold was live, but this project's
     * standing discipline is to re-verify rather than assume (rule 9).
     *
     * Idempotent: calling this again on an already-confirmed booking is a
     * safe no-op (returns the booking as-is, does not re-dispatch
     * BookingConfirmed) — guards against a retried request double-firing
     * the confirmation email.
     *
     * @throws VehicleNotAvailableException
     */
    public function confirmPending(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            Vehicle::where('id', $booking->vehicle_id)->lockForUpdate()->firstOrFail();

            $fresh = $booking->fresh();

            if ($fresh === null) {
                throw new VehicleNotAvailableException($booking->vehicle_id);
            }

            if ($fresh->status !== 'pending') {
                return $fresh;
            }

            $availabilityRequest = new AvailabilityCheckRequest(
                vehicleId: $fresh->vehicle_id,
                pickupAt: $fresh->pickup_at,
                returnAt: $fresh->return_at,
                pickupLocationId: $fresh->pickup_location_id,
                excludingBookingId: $fresh->id,
            );

            $available = FilterRegistry::apply('booking.availabilityCheck', $availabilityRequest);

            if ($available === false) {
                throw new VehicleNotAvailableException($fresh->vehicle_id);
            }

            $fresh->update([
                'status' => 'confirmed',
                'hold_expires_at' => null,
            ]);

            $confirmed = $fresh->fresh();

            BookingConfirmed::dispatch($confirmed);

            return $confirmed;
        });
    }

    /**
     * Shared by create() and createPending(): locks the vehicle, checks
     * availability and (for a registered user) driver eligibility, and
     * computes the price breakdown. Does not persist anything.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Vehicle, 1: PriceBreakdown}
     *
     * @throws VehicleNotAvailableException
     * @throws DriverNotEligibleException
     */
    private function validateAndPrice(array $attributes): array
    {
        $vehicle = Vehicle::where('id', $attributes['vehicle_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $pickupAt = Carbon::parse($attributes['pickup_at']);
        $returnAt = Carbon::parse($attributes['return_at']);

        $availabilityRequest = new AvailabilityCheckRequest(
            vehicleId: $vehicle->id,
            pickupAt: $pickupAt,
            returnAt: $returnAt,
            pickupLocationId: (int) $attributes['pickup_location_id'],
        );

        $available = FilterRegistry::apply('booking.availabilityCheck', $availabilityRequest);

        if ($available === false) {
            throw new VehicleNotAvailableException($vehicle->id);
        }

        $userId = $attributes['user_id'] ?? null;

        if ($userId !== null) {
            $eligibilityRequest = new DriverEligibilityCheckRequest(
                userId: (int) $userId,
                vehicleCategory: $vehicle->category,
                pickupAt: $pickupAt,
            );

            $eligible = FilterRegistry::apply('booking.driverEligibilityCheck', $eligibilityRequest);

            if ($eligible === false) {
                throw DriverNotEligibleException::forUser((int) $userId, $vehicle->category);
            }
        }

        $priceRequest = new PriceCalculationRequest(
            vehicleId: $vehicle->id,
            pickupAt: $pickupAt,
            returnAt: $returnAt,
        );

        /** @var PriceBreakdown $breakdown */
        $breakdown = FilterRegistry::apply(
            'booking.priceCalculation',
            new PriceBreakdown($priceRequest),
        );

        return [$vehicle, $breakdown];
    }
}
