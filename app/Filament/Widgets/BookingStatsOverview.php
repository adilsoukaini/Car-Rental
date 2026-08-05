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
        return [
            Stat::make('Total Booking Value', number_format((float) $this->realBookings()->sum('total_price'), 2).' MAD')
                ->description('Confirmed+ bookings — committed, not necessarily charged yet'),
            Stat::make('Bookings This Month', (string) $this->realBookings()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count()),
            Stat::make('Avg Booking Value', number_format((float) ($this->realBookings()->avg('total_price') ?? 0), 2).' MAD'),
            Stat::make('Distinct Customers', (string) $this->distinctCustomerCount()),
        ];
    }

    /** @return Builder<Booking> */
    private function realBookings(): Builder
    {
        return Booking::whereIn('status', ['confirmed', 'checked_out', 'returned']);
    }

    private function distinctCustomerCount(): int
    {
        $registered = $this->realBookings()->whereNotNull('user_id')->distinct()->count('user_id');

        $guests = $this->realBookings()
            ->whereNull('user_id')
            ->whereNotNull('guest_email')
            ->where('guest_email', '!=', '')
            ->distinct()
            ->count('guest_email');

        return $registered + $guests;
    }
}
