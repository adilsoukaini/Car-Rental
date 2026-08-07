import CheckoutLayout from '@/Layouts/CheckoutLayout';
import { PageProps, Vehicle } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { AlertCircle, Calendar, Check, Lock, MapPin, User } from 'lucide-react';
import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';

interface PriceBreakdown {
    days: number;
    dailyRate: number;
    discountPercent: number;
    totalPrice: number;
    depositAmount: number;
}

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString('fr-FR', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** Stitch input styling: #F4F7FA is a Stitch-specific field fill kept as a direct hex (part of the visual identity, not a theme token). */
const inputBase =
    'w-full bg-[#F4F7FA] border border-border px-4 py-3 text-sm text-text placeholder:text-textMuted focus:border-secondary focus:ring-1 focus:ring-secondary outline-none transition-all';
const inputClass = `${inputBase} rounded-interactive`;
const phoneInputClass = `${inputBase} rounded-r-interactive`;
const phonePrefixClass =
    'inline-flex items-center rounded-l-interactive border border-r-0 border-border bg-[#F4F7FA] px-3 text-sm text-textMuted';

/** Low-opacity tints — opacity modifiers on CSS-var theme tokens don't generate classes in this Tailwind v3 setup, so tint with color-mix inline. */
const tintSecondary = { backgroundColor: 'color-mix(in srgb, var(--color-secondary) 12%, transparent)' };
const tintPrimary = { backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)' };
const tintDanger = { backgroundColor: 'color-mix(in srgb, var(--color-danger) 6%, transparent)' };

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

    // The backend validates a single guest_name; the Stitch design splits it
    // into Prénom + Nom, so those two inputs drive local state that we
    // combine into guest_name on submit (setData updates Inertia's dataRef
    // synchronously, so the combined value is what actually gets posted).
    const [firstName, setFirstName] = useState('');
    const [lastName, setLastName] = useState('');

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
        setData('guest_name', `${firstName} ${lastName}`.trim());
        post(route('bookings.store', vehicle.id));
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
                        <p className="font-semibold">Ce véhicule n'est plus disponible pour ces dates.</p>
                        <Link href={route('vehicles.show', vehicle.id)} className="mt-1 inline-block underline">
                            Retourner et choisir d'autres dates
                        </Link>
                    </div>
                </div>
            )}

            {available && (
                <>
                    <div className="grid grid-cols-1 items-start gap-6 pb-24 lg:grid-cols-12 lg:pb-0">
                        {/* Left — forms */}
                        <div className="space-y-6 lg:col-span-7">
                            <form id="main-checkout-form" onSubmit={submit}>
                                {/* Personal info card */}
                                <section className="rounded-container bg-surface p-6 shadow-resting">
                                    <h2 className="mb-4 flex items-center gap-2 font-display text-lg font-semibold text-text">
                                        <User className="h-5 w-5 text-textMuted" aria-hidden="true" />
                                        Informations personnelles
                                    </h2>

                                    {user ? (
                                        <div className="flex items-center gap-3 rounded-interactive bg-background p-4">
                                            <div
                                                className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-white"
                                                style={tintPrimary}
                                            >
                                                <User className="h-5 w-5" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-semibold text-text">
                                                    Vous êtes connecté(e) en tant que {user.name}
                                                </p>
                                                <p className="text-sm text-textMuted">{user.email}</p>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div>
                                                    <label htmlFor="firstName" className="mb-1 block text-sm font-medium text-text">
                                                        Prénom
                                                    </label>
                                                    <input
                                                        id="firstName"
                                                        type="text"
                                                        value={firstName}
                                                        onChange={(e) => setFirstName(e.target.value)}
                                                        className={inputClass}
                                                        placeholder="Votre prénom"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <label htmlFor="lastName" className="mb-1 block text-sm font-medium text-text">
                                                        Nom
                                                    </label>
                                                    <input
                                                        id="lastName"
                                                        type="text"
                                                        value={lastName}
                                                        onChange={(e) => setLastName(e.target.value)}
                                                        className={inputClass}
                                                        placeholder="Votre nom"
                                                        required
                                                    />
                                                </div>
                                            </div>
                                            {errors.guest_name && (
                                                <p className="text-sm text-danger">{errors.guest_name}</p>
                                            )}

                                            <div>
                                                <label htmlFor="guest_email" className="mb-1 block text-sm font-medium text-text">
                                                    Email
                                                </label>
                                                <input
                                                    id="guest_email"
                                                    type="email"
                                                    value={data.guest_email}
                                                    onChange={(e) => setData('guest_email', e.target.value)}
                                                    className={inputClass}
                                                    placeholder="vous@email.com"
                                                    required
                                                />
                                                {errors.guest_email && (
                                                    <p className="mt-1 text-sm text-danger">{errors.guest_email}</p>
                                                )}
                                            </div>

                                            <div>
                                                <label htmlFor="guest_phone" className="mb-1 block text-sm font-medium text-text">
                                                    Téléphone
                                                </label>
                                                <div className="flex">
                                                    <span className={phonePrefixClass}>+212</span>
                                                    <input
                                                        id="guest_phone"
                                                        type="tel"
                                                        value={data.guest_phone}
                                                        onChange={(e) => setData('guest_phone', e.target.value)}
                                                        className={phoneInputClass}
                                                        placeholder="6XX XXX XXX"
                                                        required
                                                    />
                                                </div>
                                                {errors.guest_phone && (
                                                    <p className="mt-1 text-sm text-danger">{errors.guest_phone}</p>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Hidden dates the controller validates */}
                                    <input type="hidden" name="pickup_at" value={data.pickup_at} />
                                    <input type="hidden" name="return_at" value={data.return_at} />

                                    {errors.pickup_at && (
                                        <div
                                            className="mt-4 rounded-interactive border border-danger p-3 text-sm text-danger"
                                            style={tintDanger}
                                        >
                                            <p>{errors.pickup_at}</p>
                                            {user && driverVerificationStatus !== 'approved' && (
                                                <p className="mt-1">
                                                    Si cela concerne l'éligibilité conducteur,{' '}
                                                    <Link
                                                        href={route('driver-verification.show')}
                                                        className="underline"
                                                    >
                                                        complétez votre vérification
                                                    </Link>
                                                    .
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </section>

                                {/* Dates card — read-only trip summary (inside the form) */}
                                <section className="mt-6 rounded-container bg-surface p-6 shadow-resting">
                                    <div className="space-y-4">
                                        <div className="flex items-start gap-3">
                                            <div
                                                className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full"
                                                style={tintSecondary}
                                            >
                                                <MapPin className="h-4 w-4 text-secondary" aria-hidden="true" />
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold uppercase tracking-wide text-textMuted">
                                                    Prise en charge
                                                </p>
                                                <p className="text-sm text-text">{formatDateTime(pickupAt)}</p>
                                                {vehicle.location && (
                                                    <p className="text-sm text-textMuted">
                                                        {vehicle.location.name}, {vehicle.location.city}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div
                                                className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full"
                                                style={tintPrimary}
                                            >
                                                <Calendar className="h-4 w-4 text-primary" aria-hidden="true" />
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold uppercase tracking-wide text-textMuted">
                                                    Retour
                                                </p>
                                                <p className="text-sm text-text">{formatDateTime(returnAt)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </form>
                        </div>

                        {/* Right — sticky summary card (desktop only) */}
                        <div className="hidden lg:col-span-5 lg:block">
                            <div className="sticky top-24 overflow-hidden rounded-container border border-border bg-surface shadow-raised">
                                {/* Vehicle image + name */}
                                <div className="border-b border-border p-6">
                                    <div className="h-40 w-full overflow-hidden rounded-interactive bg-background">
                                        {vehicle.primary_image ? (
                                            <img
                                                src={vehicle.primary_image.url}
                                                alt={vehicle.primary_image.alt_text ?? `${vehicle.make} ${vehicle.model}`}
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <VehiclePlaceholderIcon className="h-full w-full p-8" />
                                        )}
                                    </div>
                                    <p className="mt-3 text-xs font-medium uppercase tracking-wider text-textMuted">
                                        {vehicle.category}
                                    </p>
                                    <h2 className="font-display text-lg font-semibold text-text">
                                        {vehicle.make} {vehicle.model}
                                    </h2>
                                </div>

                                {/* Trip details */}
                                <div className="space-y-4 border-b border-border p-6">
                                    <div className="flex items-start gap-3">
                                        <div
                                            className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full"
                                            style={tintSecondary}
                                        >
                                            <MapPin className="h-4 w-4 text-secondary" aria-hidden="true" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-text">
                                                {vehicle.location?.name ?? 'Prise en charge'}
                                            </p>
                                            <p className="text-xs text-textMuted">{formatDateTime(pickupAt)}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <div
                                            className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full"
                                            style={tintPrimary}
                                        >
                                            <Calendar className="h-4 w-4 text-primary" aria-hidden="true" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-text">Retour</p>
                                            <p className="text-xs text-textMuted">{formatDateTime(returnAt)}</p>
                                        </div>
                                    </div>
                                    <div className="flex justify-between border-t border-dashed border-border pt-2">
                                        <span className="text-sm text-textMuted">Durée</span>
                                        <span className="text-sm font-medium text-text">
                                            {priceBreakdown.days} jour{priceBreakdown.days > 1 ? 's' : ''}
                                        </span>
                                    </div>
                                </div>

                                {/* Price breakdown */}
                                <div className="space-y-2 bg-background p-6">
                                    <h3 className="mb-2 text-sm font-semibold text-text">Détails du prix</h3>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-textMuted">
                                            {priceBreakdown.dailyRate.toFixed(0)} DH × {priceBreakdown.days} jour
                                            {priceBreakdown.days > 1 ? 's' : ''}
                                        </span>
                                        <span className="text-text">
                                            {(priceBreakdown.dailyRate * priceBreakdown.days).toFixed(0)} DH
                                        </span>
                                    </div>
                                    {priceBreakdown.discountPercent > 0 && (
                                        <div className="flex justify-between text-sm text-success">
                                            <span>Remise ({priceBreakdown.discountPercent}%)</span>
                                            <span>
                                                -
                                                {(
                                                    (priceBreakdown.dailyRate *
                                                        priceBreakdown.days *
                                                        priceBreakdown.discountPercent) /
                                                    100
                                                ).toFixed(0)}{' '}
                                                DH
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-sm">
                                        <span className="text-textMuted">Assurance incluse</span>
                                        <span className="flex items-center gap-1 text-success">
                                            <Check className="h-3.5 w-3.5" aria-hidden="true" />
                                            Incluse
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-textMuted">Caution</span>
                                        <span className="text-text">{priceBreakdown.depositAmount.toFixed(0)} DH</span>
                                    </div>
                                </div>

                                {/* Total + CTA */}
                                <div className="mt-auto border-t border-border bg-surface p-6">
                                    <div className="flex items-baseline justify-between">
                                        <span className="text-sm text-textMuted">Total</span>
                                        <span className="font-display text-3xl font-bold text-text">
                                            {priceBreakdown.totalPrice.toFixed(0)} DH
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-textMuted">Taxes incluses</p>
                                    <button
                                        type="submit"
                                        form="main-checkout-form"
                                        disabled={processing}
                                        className="mt-4 flex w-full items-center justify-center gap-2 rounded-interactive bg-primary py-4 font-semibold text-onPrimary transition-colors hover:bg-primaryHover disabled:opacity-50"
                                    >
                                        {processing ? (
                                            'Traitement en cours...'
                                        ) : (
                                            <>
                                                <Lock className="h-4 w-4" aria-hidden="true" />
                                                Confirmer et payer
                                            </>
                                        )}
                                    </button>
                                    <p className="mt-2 text-center text-xs text-textMuted">
                                        Aucun paiement requis maintenant
                                    </p>
                                    <p className="mt-1 text-center text-xs text-textMuted">
                                        En confirmant, vous acceptez les conditions générales
                                    </p>
                                </div>
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
