import PublicLayout from '@/Layouts/PublicLayout';
import { LayoutSlot } from '@/layoutComponentRegistry';
import { ReviewsData } from '@/Widgets/VehicleReviewsCardList';
import { Vehicle, VehicleAttribute, VehicleGalleryImage } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, Link, router } from '@inertiajs/react';
import Breadcrumbs from '@/Components/Breadcrumbs';
import EmptyState from '@/Components/EmptyState';
import Text from '@/Components/Text';
import VehicleRecommendations from '@/Widgets/VehicleRecommendations';
import { Car, Check, Cog, DoorOpen, Gauge, Star, Users, Wind } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

/**
 * Presentational labels for the specs grid + pills. The raw stored values are
 * lowercase English enum-ish strings; these map the common ones to canonical
 * English display strings, which useTranslation() then localizes (French for
 * the current storefront), with a capitalize fallback for any value not in the
 * map.
 */
const CATEGORY_LABELS: Record<string, string> = { suv: 'SUV' };
const TRANSMISSION_LABELS: Record<string, string> = {
    automatic: 'Automatic',
    manual: 'Manual',
};
const FUEL_LABELS: Record<string, string> = {
    petrol: 'Petrol',
    diesel: 'Diesel',
    electric: 'Electric',
    hybrid: 'Hybrid',
};

const INCLUDED_FEATURES = [
    'Full coverage insurance',
    'Unlimited mileage',
    '24/7 assistance',
    'Free cancellation (up to 48h)',
];

const formatLabel = (value: string): string =>
    value.charAt(0).toUpperCase() + value.slice(1);

export default function Show({
    vehicle,
    galleryImages = [],
    reviewsData,
    attributes = [],
    recommendations = [],
}: {
    vehicle: Vehicle | null;
    galleryImages: VehicleGalleryImage[];
    reviewsData: ReviewsData;
    attributes: VehicleAttribute[];
    recommendations: import('@/Widgets/VehicleRecommendations').VehicleRecommendation[];
}) {
    const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
    const dayAfter = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);

    const [pickupAt, setPickupAt] = useState(tomorrow);
    const [returnAt, setReturnAt] = useState(dayAfter);
    const { t } = useTranslation();

    if (!vehicle) {
        return (
            <PublicLayout>
                <Head title={t('Vehicle not found')} />

                <div className="mx-auto max-w-lg p-8">
                    <Breadcrumbs
                        items={[{ label: t('Our Fleet'), href: route('vehicles.index') }]}
                        className="mb-4"
                    />

                    <EmptyState
                        icon={<Car className="h-10 w-10" />}
                        title={t('Vehicle not found')}
                        description={t('This vehicle may no longer be available.')}
                        action={
                            <Link
                                href={route('vehicles.index')}
                                className="text-sm font-medium text-primary hover:text-primaryHover"
                            >
                                {t('Browse our fleet')}
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

    const specs = [
        { icon: Users, label: `${vehicle.seat_count} ${t('seats')}`, available: vehicle.seat_count != null },
        {
            icon: Cog,
            label: vehicle.transmission_type
                ? t(TRANSMISSION_LABELS[vehicle.transmission_type] ?? formatLabel(vehicle.transmission_type))
                : '',
            available: Boolean(vehicle.transmission_type),
        },
        {
            icon: Wind,
            label: t('Air conditioning'),
            available: vehicle.air_conditioning === true,
        },
        {
            icon: DoorOpen,
            label: vehicle.door_count != null ? `${vehicle.door_count} ${t('doors')}` : '',
            available: vehicle.door_count != null,
        },
        {
            icon: Gauge,
            label: vehicle.fuel_type
                ? t(FUEL_LABELS[vehicle.fuel_type] ?? formatLabel(vehicle.fuel_type))
                : '',
            available: Boolean(vehicle.fuel_type),
        },
    ].filter((spec) => spec.available && spec.label !== '');

    return (
        <PublicLayout>
            {/* Mirrors the server-side `seo` prop (VehicleController::show)
                so client-side navigations keep the same SEO title. */}
            <Head title={`${vehicle.make} ${vehicle.model} — Rent from ${price} MAD/day`} />

            <div className="mx-auto max-w-7xl p-8">
                <Breadcrumbs
                    items={[
                        { label: t('Our Fleet'), href: route('vehicles.index') },
                        { label: `${vehicle.make} ${vehicle.model}` },
                    ]}
                    className="mb-4"
                />

                <Link
                    href={route('vehicles.index')}
                    className="mb-6 inline-block rounded-interactive text-sm text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                >
                    &larr; {t('Back to fleet')}
                </Link>

                <div className="grid gap-8 lg:grid-cols-[1.15fr_1fr]">
                    {/* Left column: gallery + included + booking form */}
                    <div className="space-y-6">
                        {/* Gallery renders through the vehicle-gallery layout
                            variant — LayoutSlot resolves
                            activeLayoutVariants['vehicle-gallery'] to either
                            the single-hero (default) or carousel component
                            and passes the shared gallery images through. */}
                        <LayoutSlot name="vehicle-gallery" images={galleryImages} />

                        <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                            <Text variant="h2">{t('Included in the price')}</Text>
                            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {INCLUDED_FEATURES.map((feature) => (
                                    <div key={feature} className="flex items-center gap-3">
                                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/10 text-success">
                                            <Check className="h-4 w-4" strokeWidth={2.5} aria-hidden="true" />
                                        </span>
                                        <span className="text-sm text-text">{t(feature)}</span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                            <Text variant="h2">{t('Book')}</Text>
                            <form onSubmit={submit} className="mt-4 space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label htmlFor="pickup-date" className="mb-1 block text-sm text-textMuted">{t('Pickup date')}</label>
                                        <input
                                            id="pickup-date"
                                            type="datetime-local"
                                            value={pickupAt}
                                            onChange={(e) => setPickupAt(e.target.value)}
                                            className="w-full rounded-interactive border border-border bg-background px-3 py-2 text-text focus:border-focusRing focus:outline-none focus:ring-focusRing"
                                            required
                                            aria-required="true"
                                        />
                                    </div>

                                    <div>
                                        <label htmlFor="return-date" className="mb-1 block text-sm text-textMuted">{t('Return date')}</label>
                                        <input
                                            id="return-date"
                                            type="datetime-local"
                                            value={returnAt}
                                            onChange={(e) => setReturnAt(e.target.value)}
                                            className="w-full rounded-interactive border border-border bg-background px-3 py-2 text-text focus:border-focusRing focus:outline-none focus:ring-focusRing"
                                            required
                                            aria-required="true"
                                        />
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    className="w-full rounded-interactive bg-primary px-4 py-3 font-body font-semibold text-onPrimary shadow-resting hover:bg-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                                >
                                    {t('Continue booking')}
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
                                        ? t(TRANSMISSION_LABELS[vehicle.transmission_type] ?? formatLabel(vehicle.transmission_type))
                                        : '—'}
                                </span>
                                <span className="rounded-pill border border-border bg-background px-2 py-1 text-xs text-textMuted">
                                    {vehicle.fuel_type
                                        ? t(FUEL_LABELS[vehicle.fuel_type] ?? formatLabel(vehicle.fuel_type))
                                        : '—'}
                                </span>
                            </div>

                            {reviewsData.reviewCount > 0 ? (
                                <p className="mt-3 flex items-center gap-1.5 text-sm text-textMuted">
                                    <Star className="h-4 w-4 fill-secondary text-secondary" aria-hidden="true" />
                                    <span className="font-semibold text-text">{reviewsData.averageRating.toFixed(1)}</span>
                                    <span>({reviewsData.reviewCount} {t('reviews')})</span>
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
                                                <Icon className="h-5 w-5" aria-hidden="true" />
                                            </span>
                                            <span className="text-sm text-text">{spec.label}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Custom specs from the vehicle-attributes plugin —
                            resolved via the vehicle.attributes filter. Hidden
                            entirely when the plugin is disabled or the vehicle
                            carries no attribute values. */}
                        {attributes.length > 0 ? (
                            <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                                <Text variant="h2">{t('Features')}</Text>
                                <dl className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    {attributes.map((attr) => (
                                        <div
                                            key={attr.key}
                                            className="flex items-center justify-between gap-3"
                                        >
                                            <dt className="text-sm text-textMuted">{attr.label}</dt>
                                            <dd className="text-sm font-medium text-text">
                                                {attr.type === 'boolean'
                                                    ? attr.value
                                                        ? t('Yes')
                                                        : t('No')
                                                    : String(attr.value)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        ) : null}

                        <div className="rounded-container border border-border bg-surface p-6 shadow-raised">
                            <p className="font-mono text-3xl font-bold text-text">
                                {price} <span className="text-base font-semibold text-textMuted">{t('DH / day')}</span>
                            </p>

                            <Link
                                href={route('bookings.checkout', vehicle.id)}
                                data={{ pickup_at: pickupAt, return_at: returnAt }}
                                className="mt-4 block w-full rounded-interactive bg-primary px-4 py-3 text-center font-body font-semibold text-onPrimary shadow-resting transition hover:bg-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                            >
                                {t('Continue booking')}
                            </Link>

                            <p className="mt-2 text-center text-xs text-textMuted">
                                {t('No payment required now')}
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

                <VehicleRecommendations vehicles={recommendations} />
            </div>
        </PublicLayout>
    );
}
