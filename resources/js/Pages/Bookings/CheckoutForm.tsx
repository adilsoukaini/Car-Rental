import { PageProps, User, Vehicle } from '@/types';
import { Link } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Calendar, MapPin, Tag, User as UserIcon } from 'lucide-react';
import {
    formatDateTime,
    inputClass,
    phoneInputClass,
    phonePrefixClass,
    tintDanger,
    tintPrimary,
    tintSecondary,
} from '@/Pages/Bookings/checkoutShared';

export interface CheckoutFormData {
    guest_name: string;
    guest_email: string;
    guest_phone: string;
    pickup_at: string;
    return_at: string;
    promo_code: string;
}

/**
 * The shared checkout form — personal-info section plus the read-only trip
 * dates card, inside the `<form id="main-checkout-form">` that both checkout
 * layout variants submit. The submit buttons live outside this form (in the
 * summary card / mobile bar) and target it via the `form` attribute, so the
 * form itself carries no CTA.
 */
interface CheckoutFormProps {
    vehicle: Vehicle;
    user: User | null;
    driverVerificationStatus: PageProps['driverVerificationStatus'];
    firstName: string;
    lastName: string;
    onFirstNameChange: (value: string) => void;
    onLastNameChange: (value: string) => void;
    data: CheckoutFormData;
    onDataChange: (key: keyof CheckoutFormData, value: string) => void;
    errors: Partial<Record<keyof CheckoutFormData, string>>;
    pickupAt: string;
    returnAt: string;
    onSubmit: FormEventHandler;
    promoError: string | null;
    promoApplied: boolean;
    onApplyPromo: () => void;
}

export default function CheckoutForm({
    vehicle,
    user,
    driverVerificationStatus,
    firstName,
    lastName,
    onFirstNameChange,
    onLastNameChange,
    data,
    onDataChange,
    errors,
    pickupAt,
    returnAt,
    onSubmit,
    promoError,
    promoApplied,
    onApplyPromo,
}: CheckoutFormProps) {
    return (
        <form id="main-checkout-form" onSubmit={onSubmit}>
            {/* Personal info card */}
            <section className="rounded-container bg-surface p-6 shadow-resting">
                <h2 className="mb-4 flex items-center gap-2 font-display text-lg font-semibold text-text">
                    <UserIcon className="h-5 w-5 text-textMuted" aria-hidden="true" />
                    Informations personnelles
                </h2>

                {user ? (
                    <div className="flex items-center gap-3 rounded-interactive bg-background p-4">
                        <div
                            className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-white"
                            style={tintPrimary}
                        >
                            <UserIcon className="h-5 w-5" />
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
                                    onChange={(e) => onFirstNameChange(e.target.value)}
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
                                    onChange={(e) => onLastNameChange(e.target.value)}
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
                                onChange={(e) => onDataChange('guest_email', e.target.value)}
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
                                    onChange={(e) => onDataChange('guest_phone', e.target.value)}
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

            {/* Promo code card — optional discount code, applied via a
                server-side re-preview (router.get with preserveState) so the
                price summary reflects the discount before payment. */}
            <section className="mt-6 rounded-container bg-surface p-6 shadow-resting">
                <h2 className="mb-4 flex items-center gap-2 font-display text-lg font-semibold text-text">
                    <Tag className="h-5 w-5 text-textMuted" aria-hidden="true" />
                    Code promo
                </h2>
                <div className="flex gap-2">
                    <input
                        id="promo_code"
                        type="text"
                        value={data.promo_code}
                        onChange={(e) => onDataChange('promo_code', e.target.value)}
                        className={inputClass}
                        placeholder="Ex : WELCOME10 (optionnel)"
                    />
                    <button
                        type="button"
                        onClick={onApplyPromo}
                        className="shrink-0 rounded-interactive bg-secondary px-4 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    >
                        Appliquer
                    </button>
                </div>
                {promoError && (
                    <p className="mt-2 text-sm text-danger">{promoError}</p>
                )}
                {promoApplied && !promoError && (
                    <p className="mt-2 text-sm text-success">Code promo appliqué</p>
                )}
            </section>
        </form>
    );
}
