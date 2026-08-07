import CheckoutLayout from '@/Layouts/CheckoutLayout';
import { PageProps, Vehicle } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface PriceBreakdown {
    days: number;
    dailyRate: number;
    discountPercent: number;
    totalPrice: number;
    depositAmount: number;
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

export default function Checkout({
    vehicle,
    pickupAt,
    returnAt,
    available,
    priceBreakdown,
}: {
    vehicle: Vehicle;
    pickupAt: string;
    returnAt: string;
    available: boolean;
    priceBreakdown: PriceBreakdown;
}) {
    const { auth, driverVerificationStatus } = usePage<PageProps>().props;
    const user = auth.user;

    const { data, setData, post, processing, errors } = useForm<{
        guest_name: string;
        guest_email: string;
        guest_phone: string;
        pickup_at: string;
        return_at: string;
    }>({
        guest_name: '',
        guest_email: '',
        guest_phone: '',
        pickup_at: pickupAt,
        return_at: returnAt,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('bookings.store', vehicle.id));
    };

    return (
        <CheckoutLayout>
            <Head title="Confirm your booking" />

            <div className="mx-auto max-w-lg p-8">
                <h1 className="mb-6 font-display text-3xl font-bold text-text">
                    Confirm your booking
                </h1>

                <div className="mb-6 space-y-4 rounded-container border border-border bg-surface p-6 shadow-resting">
                    <div>
                        <h2 className="mb-1 text-xs font-semibold uppercase tracking-wide text-textMuted">
                            Vehicle
                        </h2>
                        <p className="text-sm text-text">
                            {vehicle.make} {vehicle.model} ({vehicle.year})
                        </p>
                    </div>

                    <div>
                        <h2 className="mb-1 text-xs font-semibold uppercase tracking-wide text-textMuted">
                            Pickup
                        </h2>
                        <p className="text-sm text-text">{formatDateTime(pickupAt)}</p>
                    </div>

                    <div>
                        <h2 className="mb-1 text-xs font-semibold uppercase tracking-wide text-textMuted">
                            Return
                        </h2>
                        <p className="text-sm text-text">{formatDateTime(returnAt)}</p>
                    </div>

                    <div className="border-t border-border pt-4 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-textMuted">
                                {priceBreakdown.dailyRate.toFixed(2)} &times; {priceBreakdown.days} day(s)
                                {priceBreakdown.discountPercent > 0 && ` (${priceBreakdown.discountPercent}% off)`}
                            </span>
                            <span className="text-text">{priceBreakdown.totalPrice.toFixed(2)}</span>
                        </div>
                        <div className="mt-1 flex items-center justify-between font-semibold">
                            <span className="text-text">Security deposit</span>
                            <span className="text-text">{priceBreakdown.depositAmount.toFixed(2)}</span>
                        </div>
                    </div>
                </div>

                {!available && (
                    <div className="mb-6 rounded-container border border-danger bg-surface p-4 text-sm text-danger">
                        This vehicle is not available for the selected dates. Go back and choose different
                        dates.
                    </div>
                )}

                {available && (
                    <form onSubmit={submit} className="space-y-4 rounded-container border border-border bg-surface p-6 shadow-resting">
                        {!user && (
                            <>
                                <div>
                                    <label className="mb-1 block text-sm text-textMuted">Full name</label>
                                    <input
                                        type="text"
                                        value={data.guest_name}
                                        onChange={(e) => setData('guest_name', e.target.value)}
                                        className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                                        required
                                    />
                                    {errors.guest_name && <p className="mt-1 text-sm text-danger">{errors.guest_name}</p>}
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm text-textMuted">Email</label>
                                    <input
                                        type="email"
                                        value={data.guest_email}
                                        onChange={(e) => setData('guest_email', e.target.value)}
                                        className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                                        required
                                    />
                                    {errors.guest_email && <p className="mt-1 text-sm text-danger">{errors.guest_email}</p>}
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm text-textMuted">Phone</label>
                                    <input
                                        type="tel"
                                        value={data.guest_phone}
                                        onChange={(e) => setData('guest_phone', e.target.value)}
                                        className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                                        required
                                    />
                                    {errors.guest_phone && <p className="mt-1 text-sm text-danger">{errors.guest_phone}</p>}
                                </div>
                            </>
                        )}

                        {errors.pickup_at && (
                            <div className="text-sm text-danger">
                                <p>{errors.pickup_at}</p>
                                {user && driverVerificationStatus !== 'approved' && (
                                    <p className="mt-1">
                                        If this is about driver eligibility,{' '}
                                        <Link href={route('driver-verification.show')} className="underline">
                                            complete driver verification
                                        </Link>
                                        .
                                    </p>
                                )}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-interactive bg-primary px-4 py-2 font-body text-onPrimary shadow-resting hover:bg-primaryHover"
                        >
                            Confirm booking
                        </button>
                    </form>
                )}
            </div>
        </CheckoutLayout>
    );
}
