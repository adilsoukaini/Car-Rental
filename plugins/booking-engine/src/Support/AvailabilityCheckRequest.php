<?php

declare(strict_types=1);

namespace Plugins\BookingEngine\Support;

use Carbon\CarbonInterface;

/**
 * The value passed through the `booking.availabilityCheck` filter pipeline.
 *
 * Convention (matches this project's other Pipeline-based filters): a pipe
 * that finds the request unavailable returns `false` directly (short-
 * circuiting the pipeline, per Laravel's Pipeline mechanics — not calling
 * $next stops the chain). A pipe that finds no issue calls $next($request)
 * to pass this same object, unchanged, to the next pipe. If every pipe
 * passes, FilterRegistry::apply() returns this object back (truthy);
 * otherwise it returns `false`.
 */
class AvailabilityCheckRequest
{
    public function __construct(
        public readonly int $vehicleId,
        public readonly CarbonInterface $pickupAt,
        public readonly CarbonInterface $returnAt,
        public readonly int $pickupLocationId,
        public readonly ?int $excludingBookingId = null,
    ) {}
}
