<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * A static month calendar showing vehicle bookings at a glance — pickup
 * days (green), return days (red) and the active rental period between
 * them (blue). Deliberately NOT FullCalendar: a plain HTML month grid
 * rendered through a Blade view is enough for "see availability at a
 * glance", and avoids pulling a heavy JS dependency into the admin panel.
 *
 * Month navigation is plain Livewire state (public $month / $year) with
 * three actions (previous / next / current) — no query-string params, no
 * extra routes. The rendered grid and its tooltips are plain HTML/CSS, so
 * the whole widget is one self-contained Blade view.
 *
 * Status filter matches the project's established "real booking"
 * definition (BookingStatsOverview / VehicleUtilizationTable):
 * confirmed / checked_out / returned. `pending` holds and `cancelled` /
 * `expired` bookings are excluded — a cancelled booking no longer
 * occupies the vehicle, and a pending one is only a transient hold, not
 * a committed rental. Named explicitly, not a silent difference.
 *
 * Rule 8: all overlapping bookings for the displayed month are loaded in
 * a single query (vehicle eager-loaded in a second), and the per-day
 * pickup/return/active classification is done in PHP — never one query
 * per booking.
 */
class BookingCalendarWidget extends Widget
{
    protected string $view = 'filament.widgets.booking-calendar';

    /** @var int Displayed month (1–12) — Livewire state for navigation. */
    public int $month;

    /** @var int Displayed year — Livewire state for navigation. */
    public int $year;

    /**
     * Filament v4 getter — widget spans the full column width. Deliberately
     * an override of getColumnSpan() rather than redeclaring the parent's
     * typed $columnSpan property (per this project's v4 guidance).
     */
    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    /**
     * Render the calendar immediately instead of Filament's default lazy
     * placeholder — the calendar is the point of the widget, there's no
     * expensive render to defer. Getter override (not a redeclared static
     * property) for the same v4-compatibility reason as getColumnSpan().
     */
    public static function isLazy(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;
    }

    public function goToPreviousMonth(): void
    {
        $anchor = $this->monthAnchor()->subMonth();
        $this->month = $anchor->month;
        $this->year = $anchor->year;
    }

    public function goToNextMonth(): void
    {
        $anchor = $this->monthAnchor()->addMonth();
        $this->month = $anchor->month;
        $this->year = $anchor->year;
    }

    public function goToCurrentMonth(): void
    {
        $this->month = now()->month;
        $this->year = now()->year;
    }

    private function monthAnchor(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->startOfDay();
    }

    /**
     * Build the calendar grid for the displayed month.
     *
     * @return array{
     *     monthName: string,
     *     today: string,
     *     month: int,
     *     year: int,
     *     rows: array<int, array<int, array{
     *         day: int,
     *         dateKey: string,
     *         isToday: bool,
     *         isWeekend: bool,
     *         markers: array<int, array{
     *             color: 'pickup'|'return'|'active',
     *             vehicle: string,
     *             bookingNumber: string,
     *             status: string,
     *             pickupAt: string,
     *             returnAt: string,
     *         }>,
     *     }|null>>,
     * }
     */
    public function getCalendarData(): array
    {
        $first = $this->monthAnchor();
        $monthEnd = $first->copy()->endOfMonth();

        // Single query for every booking overlapping this month, with the
        // vehicle eager-loaded in a second query (rule 8).
        $bookings = Booking::query()
            ->whereIn('status', ['confirmed', 'checked_out', 'returned'])
            ->where('pickup_at', '<=', $monthEnd->copy()->endOfDay())
            ->where('return_at', '>=', $first)
            ->with('vehicle:id,make,model')
            ->get(['id', 'booking_number', 'vehicle_id', 'pickup_at', 'return_at', 'status']);

        /** @var array<int, array{day: int, dateKey: string, isToday: bool, isWeekend: bool, markers: array<int, array<string, mixed>>}> $days */
        $days = [];

        for ($day = 1; $day <= $monthEnd->day; $day++) {
            $date = $first->copy()->day($day);

            $days[$day] = [
                'day' => $day,
                'dateKey' => $date->toDateString(),
                'isToday' => $date->isToday(),
                'isWeekend' => $date->isWeekend(),
                'markers' => [],
            ];
        }

        foreach ($bookings as $booking) {
            $periodStart = $booking->pickup_at->copy()->startOfDay()->max($first);
            $periodEnd = $booking->return_at->copy()->startOfDay()->min($monthEnd);

            // Walk every day of the booking that falls inside the displayed
            // month, classifying each as pickup / return / active.
            for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
                $day = $date->day;

                if (! isset($days[$day])) {
                    continue;
                }

                if ($date->isSameDay($booking->pickup_at)) {
                    $color = 'pickup';
                } elseif ($date->isSameDay($booking->return_at)) {
                    $color = 'return';
                } else {
                    $color = 'active';
                }

                $days[$day]['markers'][] = [
                    'color' => $color,
                    'vehicle' => trim($booking->vehicle->make.' '.$booking->vehicle->model),
                    'bookingNumber' => $booking->booking_number,
                    'status' => $booking->status,
                    'pickupAt' => $booking->pickup_at->format('M j, Y'),
                    'returnAt' => $booking->return_at->format('M j, Y'),
                ];
            }
        }

        // Assemble the grid: leading blanks to align the 1st on its weekday
        // (Carbon dayOfWeek: 0 = Sunday), then the days, then trailing
        // blanks to complete the final row of 7.
        $leadingBlanks = $first->dayOfWeek;
        $cells = array_merge(
            array_fill(0, $leadingBlanks, null),
            array_values($days),
        );

        $trailingBlanks = (7 - (count($cells) % 7)) % 7;
        $cells = array_merge($cells, array_fill(0, $trailingBlanks, null));

        return [
            'monthName' => $first->format('F Y'),
            'today' => now()->toDateString(),
            'month' => $this->month,
            'year' => $this->year,
            'rows' => array_chunk($cells, 7),
        ];
    }
}
