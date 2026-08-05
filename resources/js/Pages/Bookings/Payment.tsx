import PublicLayout from '@/Layouts/PublicLayout';
import { Head, router } from '@inertiajs/react';
import {
    Elements,
    PaymentElement,
    useElements,
    useStripe,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { FormEventHandler, useState } from 'react';

interface VehicleSummary {
    make: string;
    model: string;
    year: number;
}

interface PaymentProps {
    bookingId: number;
    vehicle: VehicleSummary;
    pickupAt: string;
    returnAt: string;
    totalPrice: number;
    depositAmount: number;
    holdExpiresAt: string | null;
    clientSecret: string;
    stripePublishableKey: string;
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

function PaymentForm({ bookingId }: { bookingId: number }) {
    const stripe = useStripe();
    const elements = useElements();
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();

        if (!stripe || !elements) {
            return;
        }

        setProcessing(true);
        setError(null);

        const { error: confirmError } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: route('bookings.confirm', bookingId),
            },
            redirect: 'if_required',
        });

        if (confirmError) {
            setError(confirmError.message ?? 'Payment could not be completed. Please try again.');
            setProcessing(false);
            return;
        }

        // No redirect was needed (the common case) — finalize on our side.
        // If a redirect WAS needed (e.g. 3D Secure), Stripe already sent
        // the browser to return_url above and this line never runs.
        router.get(route('bookings.confirm', bookingId));
    };

    return (
        <form onSubmit={submit} className="space-y-4 rounded-container border border-border bg-surface p-6 shadow-resting">
            <PaymentElement />

            {error && <p className="text-sm text-danger">{error}</p>}

            <button
                type="submit"
                disabled={!stripe || processing}
                className="w-full rounded-interactive bg-primary px-4 py-2 font-body text-onPrimary shadow-resting hover:bg-primaryHover disabled:opacity-50"
            >
                {processing ? 'Processing…' : 'Pay security deposit hold'}
            </button>
        </form>
    );
}

export default function Payment({
    bookingId,
    vehicle,
    pickupAt,
    returnAt,
    totalPrice,
    depositAmount,
    holdExpiresAt,
    clientSecret,
    stripePublishableKey,
}: PaymentProps) {
    const [stripePromise] = useState(() => loadStripe(stripePublishableKey));

    return (
        <PublicLayout>
            <Head title="Complete your payment" />

            <div className="mx-auto max-w-lg p-8">
                <h1 className="mb-6 font-display text-3xl font-bold text-text">
                    Complete your booking
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
                            <span className="text-textMuted">Total price</span>
                            <span className="text-text">{totalPrice.toFixed(2)}</span>
                        </div>
                        <div className="mt-1 flex items-center justify-between font-semibold">
                            <span className="text-text">Security deposit hold (charged now)</span>
                            <span className="text-text">{depositAmount.toFixed(2)}</span>
                        </div>
                    </div>

                    {holdExpiresAt && (
                        <p className="text-xs text-textMuted">
                            This vehicle is reserved for you until {formatDateTime(holdExpiresAt)}.
                        </p>
                    )}
                </div>

                <Elements stripe={stripePromise} options={{ clientSecret }}>
                    <PaymentForm bookingId={bookingId} />
                </Elements>
            </div>
        </PublicLayout>
    );
}
