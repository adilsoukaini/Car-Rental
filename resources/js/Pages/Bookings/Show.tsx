import PublicLayout from '@/Layouts/PublicLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { Booking, PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
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
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    // Fall back to the numeric id for pre-booking_number rows (legacy dev data).
    const reference = booking.booking_number ?? String(booking.id);
    // The confirmation email goes to the guest email, or the account email for
    // an authenticated booking — same resolution as SendBookingConfirmationEmail.
    const confirmationEmail = booking.guest_email ?? auth.user?.email;

    return (
        <PublicLayout>
            <Head title={`Booking #${reference}`} />

            <div className="mx-auto max-w-lg p-8">
                <Breadcrumbs items={[{ label: `Booking #${reference}` }]} className="mb-4" />

                <div className="mb-6 flex items-center justify-between">
                    <Text variant="h1">
                        Booking #{reference}
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

                {/* Next steps — what happens now that the booking is confirmed. */}
                <div className="mt-8 rounded-container border border-border bg-surface p-6 shadow-resting">
                    <h2 className="font-display text-lg font-semibold text-text">{t('Next steps')}</h2>
                    <ul className="mt-4 space-y-3 text-sm text-text">
                        <li className="flex items-start gap-2">
                            <span aria-hidden="true">✅</span>
                            <span>{t('Booking confirmed — your reference is')} <strong>{reference}</strong></span>
                        </li>
                        {confirmationEmail && (
                            <li className="flex items-start gap-2">
                                <span aria-hidden="true">📧</span>
                                <span>{t('A confirmation email has been sent to')} <strong>{confirmationEmail}</strong></span>
                            </li>
                        )}
                        <li className="flex items-start gap-2">
                            <span aria-hidden="true">📍</span>
                            <span>{t('Pick up your vehicle at')} <strong>{booking.pickup_location.name}</strong> {t('on')} {formatDateTime(booking.pickup_at)}</span>
                        </li>
                    </ul>
                    <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                        <Link
                            href={route('bookings.track')}
                            className="inline-flex items-center justify-center rounded-interactive border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-text transition hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                        >
                            {t('Track my booking')}
                        </Link>
                        <Link
                            href={route('vehicles.index')}
                            className="inline-flex items-center justify-center rounded-interactive bg-primary px-4 py-2.5 text-sm font-semibold text-onPrimary shadow-resting transition hover:bg-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                        >
                            {t('Browse more vehicles')}
                        </Link>
                    </div>
                </div>

                {/* What the customer must bring at pickup — standard industry
                    requirements, verified by the rental agent on-site (not
                    online). */}
                <div className="mt-8 rounded-container border border-border bg-surface p-6 shadow-resting">
                    <h2 className="font-display text-lg font-semibold text-text">
                        {t('What to bring at pickup')}
                    </h2>
                    <ul className="mt-4 space-y-3 text-sm text-text">
                        <li className="flex items-start gap-2">
                            <span aria-hidden="true">🪪</span>
                            <span>{t('Original driver\'s license (valid, held for 1+ year)')}</span>
                        </li>
                        <li className="flex items-start gap-2">
                            <span aria-hidden="true">🆔</span>
                            <span>{t('Original ID card (passport or national ID)')}</span>
                        </li>
                        <li className="flex items-start gap-2">
                            <span aria-hidden="true">💳</span>
                            <span>{t('Credit card in the main driver\'s name')}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </PublicLayout>
    );
}
