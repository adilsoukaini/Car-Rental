<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;

/**
 * Deliberately DOES count `cancelled` bookings, unlike BookingStatsOverview's
 * "Total Booking Value" — this widget answers "how many bookings did we
 * actually get, regardless of later cancellation", not "what business is
 * currently on the books". `pending`/`expired` are still excluded — those
 * never actually completed the checkout flow (an abandoned mid-payment
 * attempt isn't a booking that happened). Named explicitly, not left as a
 * silent difference from the stats widget's status filter.
 */
class BookingVolumeChart extends ChartWidget
{
    protected ?string $heading = 'Booking Volume (last 30 days)';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        /** @var array<string, int> $counts */
        $counts = Booking::whereIn('status', ['confirmed', 'checked_out', 'returned', 'cancelled'])
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (Booking $booking) => $booking->created_at->format('Y-m-d'))
            ->map(fn ($group) => $group->count())
            ->all();

        $labels = [];
        $data = [];

        for ($day = 0; $day < 30; $day++) {
            $date = $start->copy()->addDays($day);
            $key = $date->format('Y-m-d');

            $labels[] = $date->format('M j');
            $data[] = $counts[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
