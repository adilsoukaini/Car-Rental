<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deliberately labeled "Total Booking Value", not "Revenue" — this
 * project's rental total (Booking.total_price) has never actually been
 * charged to a customer anywhere in the real booking flow
 * (PaymentGateway::chargeFinal() has zero real callers; the only real
 * money movement is the deposit hold). "Revenue" would imply money
 * actually collected, which this number is not — porting the source
 * e-commerce project's StatsOverviewTemplate metric name verbatim would
 * have been technically computed but substantively misleading.
 *
 * "Real" bookings here means confirmed/checked_out/returned — excludes
 * `pending` (still mid-checkout, never committed) and `expired`
 * (abandoned checkout, released). `cancelled` is ALSO excluded here,
 * unlike the booking-volume chart widget, which deliberately DOES count
 * cancelled bookings — this widget answers "what business is currently
 * on the books", the chart answers "how many bookings did we actually
 * get, regardless of later cancellation". Two different, deliberately
 * different questions — named explicitly so neither number's assumption
 * is hidden.
 */
class BookingStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Load the real bookings once and derive every stat from the
        // collection — the previous version ran five separate aggregate
        // queries over the same table (sum, this-month count, avg, and two
        // distinct-customer counts) in one request. Rule 8 discipline applied
        // to the analytics dashboard itself: one query, PHP-side math.
        $bookings = $this->realBookings()
            ->get(['total_price', 'user_id', 'guest_email', 'created_at']);

        return [
            Stat::make('Total Booking Value', number_format((float) $bookings->sum('total_price'), 2).' MAD')
                ->description('Confirmed+ bookings — committed, not necessarily charged yet'),
            Stat::make('Bookings This Month', (string) $bookings->filter(
                fn (Booking $booking) => $booking->created_at?->isCurrentMonth()
            )->count()),
            Stat::make('Avg Booking Value', number_format((float) ($bookings->avg('total_price') ?? 0), 2).' MAD'),
            Stat::make('Distinct Customers', (string) $this->distinctCustomerCount($bookings)),
        ];
    }

    /** @return Builder<Booking> */
    private function realBookings(): Builder
    {
        return Booking::whereIn('status', ['confirmed', 'checked_out', 'returned']);
    }

    /**
     * Distinct customers = registered users (non-null user_id) + guests
     * (null user_id with a non-empty guest_email). Mirrors the original
     * SQL's COUNT(DISTINCT ...) semantics via collection uniqueness.
     *
     * @param  \Illuminate\Support\Collection<int, Booking>  $bookings
     */
    private function distinctCustomerCount(\Illuminate\Support\Collection $bookings): int
    {
        $registered = $bookings
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->count();

        $guests = $bookings
            ->whereNull('user_id')
            ->whereNotNull('guest_email')
            ->where('guest_email', '!=', '')
            ->pluck('guest_email')
            ->unique()
            ->count();

        return $registered + $guests;
    }
}
