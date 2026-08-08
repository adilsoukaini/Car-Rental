import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import { Vehicle } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Link } from '@inertiajs/react';
import { Cog, Gauge, Users } from 'lucide-react';

/**
 * Image on top, details below — matches the Stitch design source's "Nos
 * Véhicules - Project Atlas" card structure exactly: a full-bleed image
 * (no overlay badge), category label above the name, inline icon+text specs
 * (no chips), the price in "DH / jour", and a full-width "Réserver
 * maintenant" CTA.
 *
 * The whole card is a single Link (matches this project's existing
 * click-through-to-detail UX) — the CTA is a styled span, not a nested
 * <button>/<a>, to avoid invalid interactive-element nesting.
 */

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

export default function Vertical({
    vehicle,
    headingLevel = 'h2',
}: {
    vehicle: Vehicle;
    /** Heading level for the vehicle name — h2 on the fleet page, h3 when nested inside a section on the homepage. */
    headingLevel?: 'h2' | 'h3';
}) {
    const { t } = useTranslation();
    const HeadingTag = headingLevel;

    // Carry any pickup/return dates from the URL (set on the fleet page's date
    // bar, or sent by a homepage search as ?pickup=...&return=...) through to
    // the vehicle detail page as ?pickup_at=...&return_at=... so its booking
    // form pre-fills. No dates in the URL (homepage featured cards, shared
    // plain links) means no query string at all.
    const dateParams = new URLSearchParams(window.location.search);
    const pickup = dateParams.get('pickup') || '';
    const returnDate = dateParams.get('return') || '';
    const vehicleHref =
        route('vehicles.show', vehicle.id) +
        (pickup ? `?pickup_at=${pickup}${returnDate ? `&return_at=${returnDate}` : ''}` : '');

    return (
        <Link
            href={vehicleHref}
            className="group flex flex-col overflow-hidden rounded-container border border-border bg-surface shadow-resting transition hover:shadow-raised focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
        >
            <div className="relative h-48 w-full overflow-hidden bg-background">
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

            <div className="flex flex-1 flex-col p-4">
                <p className="text-xs font-medium uppercase tracking-wide text-textMuted">
                    {vehicle.category}
                </p>

                <HeadingTag className="mt-1 font-display text-lg font-semibold text-text">
                    {vehicle.make} {vehicle.model}
                </HeadingTag>

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
