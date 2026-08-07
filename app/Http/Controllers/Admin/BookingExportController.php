<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the currently-filtered Bookings as a CSV download for the admin
 * Bookings list page's "Export CSV" header action.
 *
 * Registered as a Filament panel authenticated route (see AdminPanelProvider),
 * so it inherits the panel's auth middleware; the route itself is only
 * reachable from the staff Bookings list page.
 */
class BookingExportController
{
    public function export(Request $request): StreamedResponse
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = Booking::query()
            ->with(['vehicle', 'pickupLocation', 'returnLocation', 'user'])
            ->latest('id');

        // Mirror the Bookings table's status SelectFilter exactly.
        if (filled($status)) {
            $query->where('status', $status);
        }

        // Mirror the Bookings table's global search across the columns that
        // are marked searchable (id, license plate, guest name / user name).
        if (filled($search)) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('booking_number', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vehicle', fn (Builder $vq) => $vq->where('license_plate', 'like', "%{$search}%"));
            });
        }

        $bookings = $query->get();

        $filename = 'bookings-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($bookings): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Booking #',
                'Vehicle',
                'Customer',
                'Pickup Date',
                'Return Date',
                'Pickup Location',
                'Return Location',
                'Status',
                'Total (MAD)',
                'Deposit (MAD)',
                'Created At',
            ]);

            foreach ($bookings as $booking) {
                fputcsv($out, [
                    $booking->booking_number,
                    $booking->vehicle->license_plate,
                    $booking->user->name ?? $booking->guest_name,
                    $booking->pickup_at->format('Y-m-d H:i'),
                    $booking->return_at->format('Y-m-d H:i'),
                    $booking->pickupLocation->name,
                    $booking->returnLocation->name,
                    $booking->status,
                    $booking->total_price,
                    $booking->security_deposit_amount,
                    $booking->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
