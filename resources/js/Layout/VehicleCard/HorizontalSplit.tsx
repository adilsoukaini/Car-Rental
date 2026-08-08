import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import { Vehicle } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Link } from '@inertiajs/react';
import { Cog, Gauge, Star, Users } from 'lucide-react';

/**
 * Image left, details right — matches the Stitch design source's "Nos
 * Véhicules - Modern Split Layout" card structure. Same content and CTA as
 * Vertical (full-bleed image with no overlay badge, category label above the
 * name, inline icon+text specs, "DH / jour" price, full-width "Réserver
 * maintenant" CTA), different orientation: image on the left (sm:w-2/5),
 * content on the right (sm:w-3/5).
 *
 * The whole card is a single Link — the CTA is a styled span, not a nested
 * interactive element, to avoid invalid HTML nesting.
 */

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

export default function HorizontalSplit({
    vehicle,
    headingLevel = 'h2',
}: {
    vehicle: Vehicle;
    /** Heading level for the vehicle name — h2 on the fleet page, h3 when nested inside a section on the homepage. */
    headingLevel?: 'h2' | 'h3';
}) {
    const { t } = useTranslation();
    const HeadingTag = headingLevel;

    // Carry any pickup/return dates (and optional 30-min times, GAP-1) from
    // the URL — set on the fleet page's date bar, or sent by a homepage search
    // as ?pickup=...&return=...&pickup_time=...&return_time=... — through to
    // the vehicle detail page as ?pickup_at=YYYY-MM-DDTHH:mm so its
    // datetime-local booking form pre-fills. No dates in the URL (homepage
    // featured cards, shared plain links) means no query string at all.
    const dateParams = new URLSearchParams(window.location.search);
    const pickup = dateParams.get('pickup') || '';
    const returnDate = dateParams.get('return') || '';
    const pickupTime = dateParams.get('pickup_time') || '';
    const returnTime = dateParams.get('return_time') || '';
    const pickupAt = pickup ? `${pickup}T${pickupTime || '00:00'}` : '';
    const returnAt = returnDate ? `${returnDate}T${returnTime || '00:00'}` : '';
    const vehicleHref =
        route('vehicles.show', vehicle.id) +
        (pickupAt ? `?pickup_at=${pickupAt}${returnAt ? `&return_at=${returnAt}` : ''}` : '');

    return (
        <Link
            href={vehicleHref}
            className="group flex flex-col overflow-hidden rounded-container border border-border bg-surface shadow-resting transition hover:shadow-raised focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing sm:flex-row"
        >
            <div className="relative h-48 w-full shrink-0 overflow-hidden bg-background sm:h-full sm:w-2/5">
                {vehicle.primary_image ? (
                    <img
                        src={vehicle.primary_image.url}
                        alt={vehicle.primary_image.alt_text ?? `${vehicle.make} ${vehicle.model}`}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <VehiclePlaceholderIcon />
                    </div>
                )}
            </div>

            <div className="flex w-full flex-1 flex-col p-4 sm:w-3/5">
                <p className="text-xs font-medium uppercase tracking-wide text-textMuted">
                    {vehicle.category}
                </p>

                <HeadingTag className="mt-1 font-display text-lg font-semibold text-text">
                    {vehicle.make} {vehicle.model}
                </HeadingTag>

                {/* Approved-review count + average, batch-loaded by the fleet
                    query's withReviewSummary scope (rule 8 — one aggregate
                    subquery for the whole page, not per-card). Hidden when the
                    vehicle has no approved reviews or the plugin is disabled. */}
                {vehicle.reviews_count ? (
                    <p className="mt-1 flex items-center gap-1 text-sm text-textMuted">
                        <Star className="h-3.5 w-3.5 fill-secondary text-secondary" aria-hidden="true" />
                        <span className="font-medium text-text">{Number(vehicle.reviews_avg_rating ?? 0).toFixed(1)}</span>
                        <span>({vehicle.reviews_count} {t('reviews')})</span>
                    </p>
                ) : null}

                <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-textMuted">
                    {vehicle.transmission_type && (
                        <span className="inline-flex items-center gap-1">
                            <Cog className="h-3.5 w-3.5" aria-hidden="true" />
                            {t(capitalize(vehicle.transmission_type))}
                        </span>
                    )}
                    {vehicle.seat_count > 0 && (
                        <span className="inline-flex items-center gap-1">
                            <Users className="h-3.5 w-3.5" aria-hidden="true" />
                            {vehicle.seat_count} {t('seats')}
                        </span>
                    )}
                    {vehicle.fuel_type && (
                        <span className="inline-flex items-center gap-1">
                            <Gauge className="h-3.5 w-3.5" aria-hidden="true" />
                            {t(capitalize(vehicle.fuel_type))}
                        </span>
                    )}
                </div>

                <p className="mt-3 font-display text-xl font-bold text-text">
                    {Number(vehicle.daily_rate).toFixed(0)} {t('DH / day')}
                </p>

                <span className="mt-3 w-full rounded-interactive bg-primary py-2.5 text-center text-sm font-semibold text-onPrimary group-hover:bg-primaryHover">
                    {t('Book now')}
                </span>
            </div>
        </Link>
    );
}
