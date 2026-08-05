<?php

declare(strict_types=1);

namespace App\Core\Support;

use Carbon\CarbonInterface;

/**
 * The value passed through the `booking.driverEligibilityCheck` filter.
 *
 * Lives in core (not inside booking-engine or driver-verification plugins)
 * deliberately: booking-engine's BookingCreator constructs this and calls
 * the filter, while driver-verification's pipe reads it — if this class
 * lived inside either plugin, the other would need a direct reference to
 * it, violating the "plugins never import other plugins directly" rule.
 * Core is the only namespace both plugins may depend on.
 *
 * Same short-circuit convention as AvailabilityCheckRequest: a pipe that
 * finds the driver ineligible returns `false` directly; a pipe that finds
 * no issue calls $next($request). No pipes registered (the
 * driver-verification plugin disabled or not installed) means every
 * driver is treated as eligible by default — this filter only ever
 * tightens eligibility, never loosens it.
 */
class DriverEligibilityCheckRequest
{
    public function __construct(
        public readonly ?int $userId,
        public readonly string $vehicleCategory,
        public readonly CarbonInterface $pickupAt,
    ) {}
}
