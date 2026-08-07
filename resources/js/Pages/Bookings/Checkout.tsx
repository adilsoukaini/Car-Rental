import CheckoutLayout from '@/Layouts/CheckoutLayout';
import { PageProps, Vehicle } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Calendar, Car, Lock, MapPin, Shield } from 'lucide-react';
import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';

interface PriceBreakdown {
    days: number;
    dailyRate: number;
    discountPercent: number;
    totalPrice: number;
    depositAmount: number;
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('fr-FR', {
        weekday: 'short',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
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
            <Head title="Finaliser la réservation" />

            {/* Secure checkout badge */}
            <div className="mb-6 flex items-center justify-center gap-2 text-sm text-textMuted">
                <Lock className="h-4 w-4" />
                <span>Paiement sécurisé</span>
            </div>

            <div className="mx-auto max-w-6xl px-4 pb-16">
                {!available && (
                    <div className="mb-8 rounded-container border border-danger/20 bg-danger/5 p-4 text-sm text-danger">
                        <p className="font-semibold">Ce véhicule n'est plus disponible pour ces dates.</p>
                        <Link href={route('vehicles.show', vehicle.id)} className="mt-1 inline-block underline">
                            Retourner et choisir d'autres dates
                        </Link>
                    </div>
                )}

                {available && (
                    <div className="grid items-start gap-8 lg:grid-cols-12">
                        {/* Left — Form */}
                        <div className="lg:col-span-7">
                            {/* Vehicle identity card */}
                            <div className="mb-6 flex items-center gap-4 rounded-container border border-border bg-surface p-4 shadow-resting">
                                <div className="h-20 w-28 flex-shrink-0 overflow-hidden rounded-interactive bg-background">
                                    {vehicle.primary_image ? (
                                        <img
                                            src={vehicle.primary_image.url}
                                            alt={vehicle.primary_image.alt_text ?? `${vehicle.make} ${vehicle.model}`}
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <VehiclePlaceholderIcon className="h-full w-full p-2" />
                                    )}
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xs font-medium uppercase tracking-wide text-textMuted">
                                        {vehicle.category}
                                    </p>
                                    <h2 className="font-display text-lg font-semibold text-text">
                                        {vehicle.make} {vehicle.model}
                                    </h2>
                                    <p className="text-sm text-textMuted">{vehicle.year}</p>
                                </div>
                            </div>

                            {/* Trip details card */}
                            <div className="mb-6 rounded-container border border-border bg-surface p-6 shadow-resting">
                                <h3 className="mb-4 font-display text-lg font-semibold text-text">
                                    Détails du voyage
                                </h3>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="flex items-start gap-3">
                                        <div className="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                            <MapPin className="h-4 w-4 text-primary" />
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
                                        <div className="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                            <Calendar className="h-4 w-4 text-primary" />
                                        </div>
                                        <div>
                                            <p className="text-xs font-semibold uppercase tracking-wide text-textMuted">
                                                Retour
                                            </p>
                                            <p className="text-sm text-text">{formatDateTime(returnAt)}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Personal info form */}
                            <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                                <h3 className="mb-4 font-display text-lg font-semibold text-text">
                                    {user ? 'Vos informations' : 'Informations personnelles'}
                                </h3>

                                <form id="main-checkout-form" onSubmit={submit} className="space-y-4">
                                    {!user && (
                                        <>
                                            <div>
                                                <label className="mb-1 block text-sm font-medium text-text">
                                                    Nom complet
                                                </label>
                                                <input
                                                    type="text"
                                                    value={data.guest_name}
                                                    onChange={(e) => setData('guest_name', e.target.value)}
                                                    className="w-full rounded-interactive border border-border bg-background px-3 py-2.5 text-text placeholder:text-textMuted focus:border-secondary focus:outline-none focus:ring-1 focus:ring-secondary"
                                                    placeholder="Votre nom"
                                                    required
                                                />
                                                {errors.guest_name && (
                                                    <p className="mt-1 text-sm text-danger">{errors.guest_name}</p>
                                                )}
                                            </div>

                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-sm font-medium text-text">
                                                        Email
                                                    </label>
                                                    <input
                                                        type="email"
                                                        value={data.guest_email}
                                                        onChange={(e) => setData('guest_email', e.target.value)}
                                                        className="w-full rounded-interactive border border-border bg-background px-3 py-2.5 text-text placeholder:text-textMuted focus:border-secondary focus:outline-none focus:ring-1 focus:ring-secondary"
                                                        placeholder="vous@email.com"
                                                        required
                                                    />
                                                    {errors.guest_email && (
                                                        <p className="mt-1 text-sm text-danger">{errors.guest_email}</p>
                                                    )}
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-sm font-medium text-text">
                                                        Téléphone
                                                    </label>
                                                    <input
                                                        type="tel"
                                                        value={data.guest_phone}
                                                        onChange={(e) => setData('guest_phone', e.target.value)}
                                                        className="w-full rounded-interactive border border-border bg-background px-3 py-2.5 text-text placeholder:text-textMuted focus:border-secondary focus:outline-none focus:ring-1 focus:ring-secondary"
                                                        placeholder="+212 6XX..."
                                                        required
                                                    />
                                                    {errors.guest_phone && (
                                                        <p className="mt-1 text-sm text-danger">{errors.guest_phone}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </>
                                    )}

                                    {errors.pickup_at && (
                                        <div className="rounded-interactive border border-danger/20 bg-danger/5 p-3 text-sm text-danger">
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

                                    {/* Mobile-only: price summary + CTA (visible below lg breakpoint) */}
                                    <div className="lg:hidden">
                                        <div className="mb-4 space-y-2 border-t border-border pt-4">
                                            <div className="flex justify-between text-sm">
                                                <span className="text-textMuted">
                                                    {priceBreakdown.dailyRate.toFixed(0)} DH × {priceBreakdown.days} jour{priceBreakdown.days > 1 ? 's' : ''}
                                                </span>
                                                <span className="text-text">{(priceBreakdown.dailyRate * priceBreakdown.days).toFixed(0)} DH</span>
                                            </div>
                                            {priceBreakdown.discountPercent > 0 && (
                                                <div className="flex justify-between text-sm text-success">
                                                    <span>Remise ({priceBreakdown.discountPercent}%)</span>
                                                    <span>
                                                        -{((priceBreakdown.dailyRate * priceBreakdown.days * priceBreakdown.discountPercent) / 100).toFixed(0)} DH
                                                    </span>
                                                </div>
                                            )}
                                            <div className="flex justify-between text-sm">
                                                <span className="text-textMuted">Caution (pré-autorisation)</span>
                                                <span className="text-text">{priceBreakdown.depositAmount.toFixed(0)} DH</span>
                                            </div>
                                            <div className="flex justify-between border-t border-border pt-2 font-display text-lg font-bold text-text">
                                                <span>Total</span>
                                                <span>{priceBreakdown.totalPrice.toFixed(0)} DH</span>
                                            </div>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="flex w-full items-center justify-center gap-2 rounded-interactive bg-primary px-6 py-3 font-semibold text-onPrimary shadow-resting transition hover:bg-primaryHover disabled:opacity-50"
                                        >
                                            {processing ? (
                                                'Traitement en cours...'
                                            ) : (
                                                <>
                                                    Continuer vers le paiement
                                                    <Shield className="h-4 w-4" />
                                                </>
                                            )}
                                        </button>
                                        <p className="mt-2 text-center text-xs text-textMuted">
                                            Aucun paiement requis maintenant
                                        </p>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* Right — Sticky sidebar summary (desktop only) */}
                        <div className="hidden lg:col-span-5 lg:block">
                            <div className="sticky top-24 space-y-4 rounded-container border border-border bg-surface p-6 shadow-raised">
                                <h3 className="font-display text-lg font-semibold text-text">
                                    Résumé de la réservation
                                </h3>

                                <div className="space-y-3 border-b border-border pb-4 text-sm">
                                    <div className="flex items-center gap-3">
                                        <div className="h-12 w-16 flex-shrink-0 overflow-hidden rounded-interactive bg-background">
                                            {vehicle.primary_image ? (
                                                <img
                                                    src={vehicle.primary_image.url}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <VehiclePlaceholderIcon className="h-full w-full p-1" />
                                            )}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-text">
                                                {vehicle.make} {vehicle.model}
                                            </p>
                                            <p className="text-xs text-textMuted">{vehicle.category}</p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 text-textMuted">
                                        <MapPin className="h-3.5 w-3.5" />
                                        <span>{formatDate(pickupAt)}</span>
                                    </div>
                                    <div className="flex items-center gap-2 text-textMuted">
                                        <Calendar className="h-3.5 w-3.5" />
                                        <span>{formatDate(returnAt)}</span>
                                    </div>
                                </div>

                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-textMuted">
                                            {priceBreakdown.dailyRate.toFixed(0)} DH × {priceBreakdown.days} jour{priceBreakdown.days > 1 ? 's' : ''}
                                        </span>
                                        <span className="text-text">{(priceBreakdown.dailyRate * priceBreakdown.days).toFixed(0)} DH</span>
                                    </div>
                                    {priceBreakdown.discountPercent > 0 && (
                                        <div className="flex justify-between text-success">
                                            <span>Remise ({priceBreakdown.discountPercent}%)</span>
                                            <span>
                                                -{((priceBreakdown.dailyRate * priceBreakdown.days * priceBreakdown.discountPercent) / 100).toFixed(0)} DH
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between">
                                        <span className="text-textMuted">Caution</span>
                                        <span className="text-text">{priceBreakdown.depositAmount.toFixed(0)} DH</span>
                                    </div>
                                </div>

                                <div className="flex justify-between border-t border-border pt-3 font-display text-lg font-bold text-text">
                                    <span>Total</span>
                                    <span>{priceBreakdown.totalPrice.toFixed(0)} DH</span>
                                </div>

                                <button
                                    type="submit"
                                    form="main-checkout-form"
                                    disabled={processing}
                                    className="flex w-full items-center justify-center gap-2 rounded-interactive bg-primary px-6 py-3 font-semibold text-onPrimary shadow-resting transition hover:bg-primaryHover disabled:opacity-50"
                                >
                                    {processing ? (
                                        'Traitement en cours...'
                                    ) : (
                                        <>
                                            Continuer vers le paiement
                                            <Shield className="h-4 w-4" />
                                        </>
                                    )}
                                </button>
                                <p className="text-center text-xs text-textMuted">
                                    Aucun paiement requis maintenant
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>

        </CheckoutLayout>
    );
}
