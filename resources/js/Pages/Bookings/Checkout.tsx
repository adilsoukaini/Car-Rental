import CheckoutLayout from '@/Layouts/CheckoutLayout';
import { PageProps, Vehicle } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { AlertCircle, Lock } from 'lucide-react';
import CheckoutForm, { CheckoutFormData } from '@/Pages/Bookings/CheckoutForm';
import CheckoutSummary, { PriceBreakdown } from '@/Pages/Bookings/CheckoutSummary';
import { tintDanger } from '@/Pages/Bookings/checkoutShared';

/**
 * Booking checkout with two page-layout variants, swappable by an admin via
 * the Layout Variants page (checkoutStyle slot, registered in
 * AppServiceProvider):
 *
 *  - 'checkout-sidebar'   — the original 2-column design: form (left) +
 *    sticky price summary card (right), with a fixed mobile bottom action bar.
 *  - 'checkout-vertical'  — a single centered column (max-w-2xl): form first,
 *    price summary card stacked below it, no sticky sidebar and no mobile
 *    fixed bar.
 *
 * Both variants share the exact same form state (useForm), submit handler,
 * availability warning, form content, and summary card — only the arrangement
 * differs. This page stays the entry point; the reusable pieces live in
 * CheckoutForm.tsx and CheckoutSummary.tsx.
 */
export default function Checkout({
    vehicle,
    pickupAt,
    returnAt,
    available,
    priceBreakdown,
    promoError,
    dateError,
}: {
    vehicle: Vehicle;
    pickupAt: string;
    returnAt: string;
    available: boolean;
    priceBreakdown: PriceBreakdown;
    promoError: string | null;
    dateError?: string | null;
}) {
    const { auth, driverVerificationStatus, activeLayoutVariants } = usePage<PageProps>().props;
    const user = auth.user;

    // Which checkout layout is active, shared from HandleInertiaRequests.
    // Defaults to the sidebar-flow layout when no DB row is set for this slot.
    const checkoutStyle = activeLayoutVariants?.checkoutStyle ?? 'checkout-sidebar';
    const isVertical = checkoutStyle === 'checkout-vertical';

    // The backend validates a single guest_name; the Stitch design splits it
    // into Prénom + Nom, so those two inputs drive local state that we
    // combine into guest_name on submit (setData updates Inertia's dataRef
    // synchronously, so the combined value is what actually gets posted).
    const [firstName, setFirstName] = useState('');
    const [lastName, setLastName] = useState('');

    const { data, setData, post, processing, errors } = useForm<CheckoutFormData>({
        guest_name: '',
        guest_email: '',
        guest_phone: '',
        pickup_at: pickupAt,
        return_at: returnAt,
        promo_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setData('guest_name', `${firstName} ${lastName}`.trim());
        post(route('bookings.store', vehicle.id));
    };

    // Re-fetch the price preview with the promo code so the summary card
    // reflects the discount before payment. preserveState keeps the form's
    // other fields (name/email/phone) intact across the server re-render.
    const applyPromo = () => {
        router.get(
            route('bookings.checkout', vehicle.id),
            {
                pickup_at: data.pickup_at,
                return_at: data.return_at,
                promo_code: data.promo_code,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const formProps = {
        vehicle,
        user,
        driverVerificationStatus,
        firstName,
        lastName,
        onFirstNameChange: setFirstName,
        onLastNameChange: setLastName,
        data,
        onDataChange: (key: keyof CheckoutFormData, value: string) => setData(key, value),
        errors,
        pickupAt,
        returnAt,
        onSubmit: submit,
        promoError,
        promoApplied: priceBreakdown.promoDiscount > 0,
        onApplyPromo: applyPromo,
    };

    const summaryProps = {
        vehicle,
        pickupAt,
        returnAt,
        priceBreakdown,
        processing,
    };

    return (
        <CheckoutLayout backHref={route('vehicles.show', vehicle.id)}>
            <Head title="Finaliser la réservation" />

            {!available && (
                <div
                    className="mb-6 flex items-start gap-3 rounded-container border border-danger p-4 text-sm text-danger"
                    style={tintDanger}
                >
                    <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0" />
                    <div>
                        <p className="font-semibold">
                            {dateError ?? "Ce véhicule n'est plus disponible pour ces dates."}
                        </p>
                        <Link href={route('vehicles.show', vehicle.id)} className="mt-1 inline-block underline">
                            Retourner et choisir d'autres dates
                        </Link>
                    </div>
                </div>
            )}

            {available && isVertical && (
                <div className="mx-auto max-w-2xl space-y-6 pb-16">
                    <CheckoutForm {...formProps} />
                    <CheckoutSummary {...summaryProps} />
                </div>
            )}

            {available && !isVertical && (
                <>
                    <div className="grid grid-cols-1 items-start gap-6 pb-24 lg:grid-cols-12 lg:pb-0">
                        {/* Left — forms */}
                        <div className="space-y-6 lg:col-span-7">
                            <CheckoutForm {...formProps} />
                        </div>

                        {/* Right — sticky summary card on desktop; inline below
                            the form on mobile so the line-item price breakdown
                            is visible on every screen size (QA finding: the
                            mobile bar alone showed a bare "Total" with no
                            breakdown). */}
                        <div className="lg:col-span-5">
                            <div className="lg:sticky lg:top-24">
                                <CheckoutSummary {...summaryProps} />
                            </div>
                        </div>
                    </div>

                    {/* Mobile — fixed bottom action bar */}
                    <div className="fixed bottom-0 left-0 z-40 w-full border-t border-border bg-surface px-4 py-3 shadow-[0_-4px_20px_rgba(0,0,0,0.08)] lg:hidden">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs text-textMuted">Total</p>
                                <p className="font-display text-lg font-bold text-text">
                                    {priceBreakdown.totalPrice.toFixed(0)} DH
                                </p>
                            </div>
                            <button
                                type="submit"
                                form="main-checkout-form"
                                disabled={processing}
                                className="flex items-center gap-2 rounded-interactive bg-primary px-6 py-3 font-semibold text-onPrimary transition active:scale-[0.98] disabled:opacity-50"
                            >
                                {processing ? (
                                    'Traitement...'
                                ) : (
                                    <>
                                        Payer maintenant
                                        <Lock className="h-4 w-4" aria-hidden="true" />
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </>
            )}
        </CheckoutLayout>
    );
}
