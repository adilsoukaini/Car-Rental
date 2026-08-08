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
import { Car, Check, ChevronDown, Cog, DoorOpen, Gauge, Info, MapPin, Plane, ShieldCheck, Star, Users, Wind } from 'lucide-react';
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
    'Flexible cancellation (full refund up to 7 days before)',
];

// What the customer must present at pickup. These are standard industry
// requirements (checked by the rental agent on-site, not online) — mirrors
// config('driver-verification.minimum_age_by_category').
const RENTAL_REQUIREMENTS = [
    'Valid driver\'s license held for at least 1 year',
    'Original passport or national ID card',
    'Credit card in the driver\'s name for the deposit',
];

const MIN_AGE_BY_CATEGORY: Record<string, number> = {
    economy: 21,
    suv: 21,
    van: 21,
    luxury: 25,
};

// Placeholder protections/insurance upsell — displayed at the counter, not
// charged here. The real insurance add-on plugin (CDW/SCDW buy-down, theft,
// glass, PAI) is a larger feature; this signals the option exists without
// building the upsell engine yet (DEEP-ANALYSIS Week-1 trust fix).
const PROTECTIONS = [
    'Collision damage waiver (zero deductible) — from 150 DH/day',
    'Theft and vandalism insurance — from 80 DH/day',
    'Full deductible buy-back — from 200 DH/day',
] as const;

/** True when a location is an airport (seeded names carry "Airport"/"Aéroport"). */
const isAirportLocation = (name: string | null | undefined): boolean =>
    name ? /airport|aéroport/i.test(name) : false;

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

    // Pre-fill from ?pickup_at=...&return_at=... when the user clicked through
    // from the fleet page with dates selected. The vehicle card links send
    // date-only values (YYYY-MM-DD); the form inputs are datetime-local, so a
    // date-only value is padded to midnight (T00:00) to stay a valid value.
    const urlParams = new URLSearchParams(window.location.search);
    const urlPickupAt = urlParams.get('pickup_at');
    const urlReturnAt = urlParams.get('return_at');
    const toDateTimeLocal = (value: string): string =>
        value.length === 10 ? `${value}T00:00` : value;

    const [pickupAt, setPickupAt] = useState(urlPickupAt ? toDateTimeLocal(urlPickupAt) : tomorrow);
    const [returnAt, setReturnAt] = useState(urlReturnAt ? toDateTimeLocal(urlReturnAt) : dayAfter);
    const [requirementsOpen, setRequirementsOpen] = useState(false);
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

    // daily_rate arrives as "550.00" — display as a whole number
    // ("550 DH / jour"), matching the Stitch reference.
    const price = Number(vehicle.daily_rate).toFixed(0);

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
                    {/* Left column: gallery + included + booking form. On
                        mobile (single-column grid) this sits AFTER the title/
                        price/specs column via order-2; lg resets to DOM order
                        so the gallery stays on the left as designed. */}
                    <div className="order-2 space-y-6 lg:order-none">
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

                        {/* Standard rental requirements — disclosed here (and at
                            checkout), verified by the rental agent at pickup.
                            Clear but not alarming: this is standard industry
                            practice, not a blocker. */}
                        <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                            <button
                                type="button"
                                onClick={() => setRequirementsOpen((open) => !open)}
                                aria-expanded={requirementsOpen}
                                aria-controls="rental-requirements"
                                className="flex w-full items-center justify-between gap-3 rounded-interactive text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                            >
                                <span className="flex items-center gap-2">
                                    <Info className="h-5 w-5 text-textMuted" aria-hidden="true" />
                                    <span className="font-display text-lg font-semibold text-text">
                                        {t('Requirements for this vehicle')}
                                    </span>
                                </span>
                                <ChevronDown
                                    className={`h-5 w-5 text-textMuted transition-transform ${requirementsOpen ? 'rotate-180' : ''}`}
                                    aria-hidden="true"
                                />
                            </button>
                            {requirementsOpen && (
                                <ul id="rental-requirements" className="mt-4 space-y-3">
                                    <li className="flex items-start gap-3">
                                        <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <Check className="h-3.5 w-3.5" strokeWidth={2.5} aria-hidden="true" />
                                        </span>
                                        <span className="text-sm text-text">
                                            {t('Minimum age')}:{' '}
                                            <strong>{MIN_AGE_BY_CATEGORY[vehicle.category] ?? 21}</strong>
                                        </span>
                                    </li>
                                    {RENTAL_REQUIREMENTS.map((requirement) => (
                                        <li key={requirement} className="flex items-start gap-3">
                                            <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                <Check className="h-3.5 w-3.5" strokeWidth={2.5} aria-hidden="true" />
                                            </span>
                                            <span className="text-sm text-text">{t(requirement)}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        {/* Placeholder protections/insurance upsell — signals we
                            offer CDW/SCDW buy-down + theft coverage (every
                            competitor does) without building the full insurance
                            plugin yet. Display-only; these are settled at the
                            counter. */}
                        <div className="rounded-container border border-border bg-surface p-6 shadow-resting">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="h-5 w-5 text-primary" aria-hidden="true" />
                                <Text variant="h2">{t('Additional protections (available at pickup)')}</Text>
                            </div>
                            <ul className="mt-4 space-y-3">
                                {PROTECTIONS.map((protection) => (
                                    <li key={protection} className="flex items-start gap-3">
                                        <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <Check className="h-3.5 w-3.5" strokeWidth={2.5} aria-hidden="true" />
                                        </span>
                                        <span className="text-sm text-text">{t(protection)}</span>
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-4 text-xs text-textMuted">
                                {t('These options are available at the counter when you pick up your vehicle.')}
                            </p>
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

                    {/* Right column: heading/tags/rating + specs + booking
                        card. order-1 brings title/price/specs to the top on
                        mobile, before the gallery and booking form. */}
                    <div className="order-1 space-y-6 lg:order-none">
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

                            {vehicle.location ? (
                                <p className="mt-3 flex items-center gap-1.5 text-sm text-textMuted">
                                    <MapPin className="h-4 w-4 text-primary" aria-hidden="true" />
                                    <span>
                                        {t('Pickup location')}: <span className="font-medium text-text">{vehicle.location.name}</span>
                                    </span>
                                </p>
                            ) : null}

                            {vehicle.location && isAirportLocation(vehicle.location.name) ? (
                                <div className="mt-3 flex items-start gap-2 rounded-interactive border border-border bg-background p-3 text-sm text-text">
                                    <Plane className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                                    <span>{t('Airport pickup available — your vehicle will be waiting at arrivals. Counter open 24/7.')}</span>
                                </div>
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
                                {t('A security deposit will be pre-authorized, not charged, when you book.')}
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
