import PublicLayout from '@/Layouts/PublicLayout';
import { LayoutSlot } from '@/layoutComponentRegistry';
import { ReviewsData } from '@/Widgets/VehicleReviewsCardList';
import { Vehicle, VehicleGalleryImage } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import Breadcrumbs from '@/Components/Breadcrumbs';
import EmptyState from '@/Components/EmptyState';
import Text from '@/Components/Text';
import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import { Car, Check, Cog, DoorOpen, Gauge, Star, Users, Wind } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

/**
 * Presentational labels for the specs grid + pills. The raw stored values are
 * lowercase English enum-ish strings; these map the common ones to the French
 * display language the Stitch design uses, with a capitalize fallback for any
 * value not in the map.
 */
const CATEGORY_LABELS: Record<string, string> = { suv: 'SUV' };
const TRANSMISSION_LABELS: Record<string, string> = {
    automatic: 'Automatique',
    manual: 'Manuelle',
};
const FUEL_LABELS: Record<string, string> = {
    petrol: 'Essence',
    diesel: 'Diesel',
    electric: 'Électrique',
    hybrid: 'Hybride',
};

const INCLUDED_FEATURES = [
    'Assurance tous risques',
    'Kilométrage illimité',
    'Assistance 24/7',
    'Annulation gratuite (jusqu’à 48h)',
];

const formatLabel = (value: string): string =>
    value.charAt(0).toUpperCase() + value.slice(1);

export default function Show({
    vehicle,
    galleryImages = [],
    reviewsData,
}: {
    vehicle: Vehicle | null;
    galleryImages: VehicleGalleryImage[];
    reviewsData: ReviewsData;
}) {
    const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
    const dayAfter = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);

    const [pickupAt, setPickupAt] = useState(tomorrow);
    const [returnAt, setReturnAt] = useState(dayAfter);
    const [activeImage, setActiveImage] = useState(0);

    if (!vehicle) {
        return (
            <PublicLayout>
                <Head title="Vehicle not found" />

                <div className="mx-auto max-w-lg p-8">
                    <Breadcrumbs
                        items={[{ label: 'Our Fleet', href: route('vehicles.index') }]}
                        className="mb-4"
                    />

                    <EmptyState
                        icon={<Car className="h-10 w-10" />}
                        title="Vehicle not found"
                        description="This vehicle may no longer be available."
                        action={
                            <Link
                                href={route('vehicles.index')}
                                className="text-sm font-medium text-primary hover:text-primaryHover"
                            >
                                Browse our fleet
                            </Link>
                        }
                    />
                </div>
            </PublicLayout>
        );
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        router.get(route('bookings.checkout', vehicle.id), {
            pickup_at: pickupAt,
            return_at: returnAt,
        });
    };

    // daily_rate arrives as "550.00" — trim the trailing zeros for display
    // ("550 DH / jour"), matching the Stitch reference.
    const price = String(Number(vehicle.daily_rate));

    // Clamp the active index to the actual gallery size (defensive, since
    // activeImage is state) and expose the current image for the hero.
    const safeActive = Math.min(activeImage, Math.max(galleryImages.length - 1, 0));
    const currentImage = galleryImages.length > 0 ? galleryImages[safeActive] : null;

    const specs = [
        { icon: Users, label: `${vehicle.seat_count} sièges`, available: vehicle.seat_count != null },
        {
            icon: Cog,
            label: vehicle.transmission_type
                ? TRANSMISSION_LABELS[vehicle.transmission_type] ?? formatLabel(vehicle.transmission_type)
                : '',
            available: Boolean(vehicle.transmission_type),
        },
        {
            icon: Wind,
            label: 'Climatisation',
            available: vehicle.air_conditioning === true,
        },
        {
            icon: DoorOpen,
            label: vehicle.door_count != null ? `${vehicle.door_count} portes` : '',
            available: vehicle.door_count != null,
        },
        {
            icon: Gauge,
            label: vehicle.fuel_type
                ? FUEL_LABELS[vehicle.fuel_type] ?? formatLabel(vehicle.fuel_type)
                : '',
            available: Boolean(vehicle.fuel_type),
        },
    ].filter((spec) => spec.available && spec.label !== '');

    return (
        <PublicLayout>
            <Head title={`${vehicle.make} ${vehicle.model}`} />

            <div className="mx-auto max-w-7xl p-8">
                <Breadcrumbs
                    items={[
                        { label: 'Our Fleet', href: route('vehicles.index') },
                        { label: `${vehicle.make} ${vehicle.model}` },
                    ]}
                    className="mb-4"
                />

                <Link href={route('vehicles.index')} className="mb-6 inline-block text-sm text-primary">
                    &larr; Back to fleet
                </Link>

                <div className="grid gap-8 lg:grid-cols-[1.15fr_1fr]">
                    {/* Left column: gallery + included + booking form */}
                    <div className="space-y-6">
                        <div className="rounded-container border border-border bg-surface p-4 shadow-resting">
                            <div className="overflow-hidden rounded-container bg-background">
                                {currentImage ? (
                                    <img
                                        src={currentImage.url}
                                        alt={currentImage.altText ?? `${vehicle.make} ${vehicle.model}`}
                                        className="aspect-video w-full object-cover"
                                    />
                                ) : (
                                    <div className="flex aspect-video w-full items-center justify-center">
                                        <VehiclePlaceholderIcon />
                                    </div>
                                )}
                            </div>

                            {galleryImages.length > 1 && (
                                <div className="mt-3 flex items-center justify-center gap-2">
                                    {galleryImages.map((image, index) => (
                                        <button
                                            key={index}
                                            type="button"
                                            aria-label={`Voir l'image ${index + 1}`}
                                            onClick={() => setActiveImage(index)}
                                            className={`h-2 w-2 rounded-pill transition-colors ${
                                                index === activeImage ? 'bg-primary' : 'bg-border'
                                            }`}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                            <Text variant="h3">Inclus dans le prix</Text>
                            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {INCLUDED_FEATURES.map((feature) => (
                                    <div key={feature} className="flex items-center gap-3">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/10 text-success">
                                            <Check className="h-4 w-4" strokeWidth={2.5} />
                                        </span>
                                        <span className="text-sm text-text">{feature}</span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                            <Text variant="h3">Réserver</Text>
                            <form onSubmit={submit} className="mt-4 space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-sm text-textMuted">Pickup date</label>
                                        <input
                                            type="datetime-local"
                                            value={pickupAt}
                                            onChange={(e) => setPickupAt(e.target.value)}
                                            className="w-full rounded-interactive border border-border bg-background px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                                            required
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-1 block text-sm text-textMuted">Return date</label>
                                        <input
                                            type="datetime-local"
                                            value={returnAt}
                                            onChange={(e) => setReturnAt(e.target.value)}
                                            className="w-full rounded-interactive border border-border bg-background px-3 py-2 text-text focus:border-focusRing focus:outline-none"
                                            required
                                        />
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    className="w-full rounded-interactive bg-primary px-4 py-3 font-body font-semibold text-onPrimary shadow-resting hover:bg-primaryHover"
                                >
                                    Continuer la réservation
                                </button>
                            </form>
                        </div>
                    </div>

                    {/* Right column: heading/tags/rating + specs + booking card */}
                    <div className="space-y-6">
                        <div>
                            <Text variant="h1" className="mb-3">
                                {vehicle.make} {vehicle.model} ({vehicle.year})
                            </Text>

                            <div className="flex flex-wrap gap-2">
                                <span className="rounded-pill border border-border bg-background px-2 py-1 text-xs text-textMuted">
                                    {CATEGORY_LABELS[vehicle.category] ?? formatLabel(vehicle.category)}
                                </span>
                                <span className="rounded-pill border border-border bg-background px-2 py-1 text-xs text-textMuted">
                                    {vehicle.transmission_type
                                        ? TRANSMISSION_LABELS[vehicle.transmission_type] ?? formatLabel(vehicle.transmission_type)
                                        : '—'}
                                </span>
                                <span className="rounded-pill border border-border bg-background px-2 py-1 text-xs text-textMuted">
                                    {vehicle.fuel_type
                                        ? FUEL_LABELS[vehicle.fuel_type] ?? formatLabel(vehicle.fuel_type)
                                        : '—'}
                                </span>
                            </div>

                            {reviewsData.reviewCount > 0 ? (
                                <p className="mt-3 flex items-center gap-1.5 text-sm text-textMuted">
                                    <Star className="h-4 w-4 fill-secondary text-secondary" aria-hidden="true" />
                                    <span className="font-semibold text-text">{reviewsData.averageRating.toFixed(1)}</span>
                                    <span>({reviewsData.reviewCount} avis)</span>
                                </p>
                            ) : null}
                        </div>

                        <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                            <div className="grid grid-cols-2 gap-6">
                                {specs.map((spec) => {
                                    const Icon = spec.icon;

                                    return (
                                        <div
                                            key={spec.label}
                                            className="flex flex-col items-center gap-2 text-center"
                                        >
                                            <span className="flex h-10 w-10 items-center justify-center rounded-full bg-background text-textMuted">
                                                <Icon className="h-5 w-5" />
                                            </span>
                                            <span className="text-sm text-text">{spec.label}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="rounded-container border border-border bg-surface p-6 shadow-raised">
                            <p className="font-mono text-3xl font-bold text-text">
                                {price} <span className="text-base font-semibold text-textMuted">DH / jour</span>
                            </p>

                            <Link
                                href={route('bookings.checkout', vehicle.id)}
                                data={{ pickup_at: pickupAt, return_at: returnAt }}
                                className="mt-4 block w-full rounded-interactive bg-primary px-4 py-3 text-center font-body font-semibold text-onPrimary shadow-resting transition hover:bg-primaryHover"
                            >
                                Continuer la réservation
                            </Link>

                            <p className="mt-2 text-center text-xs text-textMuted">
                                Aucun paiement requis maintenant
                            </p>
                        </div>
                    </div>
                </div>

                {/* Reviews render through the reviewDisplay layout variant —
                    LayoutSlot resolves activeLayoutVariants['reviewDisplay'] to
                    either the card-list (default) or compact component and
                    passes the shared reviews data through. */}
                <div className="mt-8">
                    <LayoutSlot name="reviewDisplay" vehicleId={vehicle.id} reviewsData={reviewsData} />
                </div>
            </div>
        </PublicLayout>
    );
}
