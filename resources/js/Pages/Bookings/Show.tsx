import PublicLayout from '@/Layouts/PublicLayout';
import { Booking } from '@/types';
import { Head } from '@inertiajs/react';
import Breadcrumbs from '@/Components/Breadcrumbs';
import Text from '@/Components/Text';

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

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Show({ booking }: { booking: Booking }) {
    return (
        <PublicLayout>
            <Head title={`Booking #${booking.id}`} />

            <div className="mx-auto max-w-lg p-8">
                <Breadcrumbs items={[{ label: `Booking #${booking.id}` }]} className="mb-4" />

                <div className="mb-6 flex items-center justify-between">
                    <Text variant="h1">
                        Booking #{booking.id}
                    </Text>
                    <span className="inline-block rounded-pill bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
                        {STATUS_LABELS[booking.status] ?? booking.status}
                    </span>
                </div>

                <div className="space-y-6 rounded-container border border-border bg-surface p-6 shadow-resting">
                    <div>
                        <h2 className="mb-1 text-xs font-semibold uppercase tracking-wide text-textMuted">
                            Vehicle
                        </h2>
                        <p className="text-sm text-text">
                            {booking.vehicle.make} {booking.vehicle.model} ({booking.vehicle.year})
                        </p>
                    </div>

                    <div>
                        <h2 className="mb-1 text-xs font-semibold uppercase tracking-wide text-textMuted">
                            Pickup
                        </h2>
                        <p className="text-sm text-text">{formatDateTime(booking.pickup_at)}</p>
                        <p className="text-sm text-textMuted">
                            {booking.pickup_location.name}, {booking.pickup_location.city}
                        </p>
                    </div>

                    <div>
                        <h2 className="mb-1 text-xs font-semibold uppercase tracking-wide text-textMuted">
                            Return
                        </h2>
                        <p className="text-sm text-text">{formatDateTime(booking.return_at)}</p>
                        <p className="text-sm text-textMuted">
                            {booking.return_location.name}, {booking.return_location.city}
                        </p>
                    </div>

                    <div className="border-t border-border pt-4">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-textMuted">Total price</span>
                            <span className="text-text">{formatAmount(booking.total_price)}</span>
                        </div>
                        {booking.security_deposit_amount && (
                            <div className="mt-1 flex items-center justify-between text-sm font-semibold">
                                <span className="text-text">Security deposit</span>
                                <span className="text-text">{formatAmount(booking.security_deposit_amount)}</span>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
