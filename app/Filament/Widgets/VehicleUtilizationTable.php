<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Vehicle;
use Filament\Widgets\Widget;

/**
 * Utilization = booked days / window days over the last 30 days, per
 * vehicle. Only confirmed/checked_out/returned bookings count as
 * "occupying" the vehicle — matching BookingStatsOverview's "real
 * booking" definition, not BookingVolumeChart's (which also counts
 * cancelled bookings for a different reason — see that widget's
 * docblock).
 *
 * A real, named simplification, not hidden: the denominator is the full
 * 30-day window for every vehicle, not reduced for time spent in
 * `maintenance` status — this project has no historical vehicle-status
 * log, so "days actually available" isn't reconstructable after the
 * fact. A vehicle that spent half the window in maintenance will show a
 * lower utilization than it "should", which is a real, stated limitation
 * of this metric, not an error in the calculation.
 *
 * Single query for ALL vehicles' overlapping bookings (rule 8 — never
 * one query per item), with the per-vehicle day-clamping done in PHP —
 * deliberately not raw SQL date arithmetic, which behaves differently
 * across SQLite/MySQL/Postgres (this project's dev DB is SQLite; the
 * production DB hasn't been chosen yet, see Phase 5's same concern about
 * portable locking).
 */
class VehicleUtilizationTable extends Widget
{
    protected string $view = 'filament.widgets.vehicle-utilization-table';

    protected int|string|array $columnSpan = 'full';

    private const WINDOW_DAYS = 30;

    /** @return array<int, array{vehicle: Vehicle, bookedDays: float, utilizationPercent: float}> */
    public function getRows(): array
    {
        // No ->startOfDay() on either end — the window must be exactly
        // WINDOW_DAYS * 86400 seconds regardless of what time "now"
        // happens to be. Snapping only windowStart to midnight (a real
        // bug caught by an exact-number test) made the window silently
        // 30.5 days instead of 30 whenever "now" wasn't midnight.
        $windowEnd = now();
        $windowStart = $windowEnd->copy()->subDays(self::WINDOW_DAYS);

        $vehicles = Vehicle::query()->get(['id', 'make', 'model', 'license_plate']);

        $overlapping = Booking::query()
            ->whereIn('status', ['confirmed', 'checked_out', 'returned'])
            ->where('pickup_at', '<', $windowEnd)
            ->where('return_at', '>', $windowStart)
            ->get(['vehicle_id', 'pickup_at', 'return_at']);

        $bookedSecondsByVehicle = [];

        foreach ($overlapping as $booking) {
            $clampedStart = $booking->pickup_at->max($windowStart);
            $clampedEnd = $booking->return_at->min($windowEnd);

            $seconds = max(0, $clampedEnd->getTimestamp() - $clampedStart->getTimestamp());

            $bookedSecondsByVehicle[$booking->vehicle_id] = ($bookedSecondsByVehicle[$booking->vehicle_id] ?? 0) + $seconds;
        }

        $windowSeconds = $windowEnd->getTimestamp() - $windowStart->getTimestamp();

        return $vehicles->map(function (Vehicle $vehicle) use ($bookedSecondsByVehicle, $windowSeconds) {
            $bookedSeconds = $bookedSecondsByVehicle[$vehicle->id] ?? 0;

            return [
                'vehicle' => $vehicle,
                'bookedDays' => round($bookedSeconds / 86400, 1),
                'utilizationPercent' => $windowSeconds > 0 ? round(($bookedSeconds / $windowSeconds) * 100, 1) : 0.0,
            ];
        })->sortByDesc('utilizationPercent')->values()->all();
    }
}
