import { Booking } from '@/types';
import { Link } from '@inertiajs/react';

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    checked_out: 'Checked out',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

function formatAmount(value: string): string {
    return parseFloat(value).toFixed(2);
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function BookingHistory({ recentBookings }: { recentBookings: Booking[] }) {
    return (
        <div className="rounded-container border border-border bg-surface p-4 shadow-resting sm:p-8">
            <h2 className="font-display text-lg font-medium text-text">Recent bookings</h2>
            <p className="mt-1 text-sm text-textMuted">Your last {recentBookings.length} bookings.</p>

            {recentBookings.length === 0 ? (
                <p className="mt-4 text-sm text-textMuted">No bookings yet.</p>
            ) : (
                <ul className="mt-4 divide-y divide-border">
                    {recentBookings.map((booking) => (
                        <li key={booking.id}>
                            <Link
                                href={route('bookings.show', booking.id)}
                                className="-mx-2 flex items-center justify-between rounded-interactive px-2 py-3 transition-colors hover:bg-background"
                            >
                                <div>
                                    <p className="text-sm font-medium text-text">
                                        {booking.vehicle.make} {booking.vehicle.model} ({booking.vehicle.year})
                                    </p>
                                    <p className="text-xs text-textMuted">{formatDate(booking.pickup_at)}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm font-semibold text-text">
                                        {formatAmount(booking.total_price)}
                                    </p>
                                    <span className="inline-block rounded-pill bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                        {STATUS_LABELS[booking.status] ?? booking.status}
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
