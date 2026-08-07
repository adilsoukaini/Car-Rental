import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import { Vehicle } from '@/types';
import { Link } from '@inertiajs/react';
import { Cog, Gauge, Users } from 'lucide-react';

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

export default function HorizontalSplit({ vehicle }: { vehicle: Vehicle }) {
    return (
        <Link
            href={route('vehicles.show', vehicle.id)}
            className="group flex flex-col overflow-hidden rounded-container border border-border bg-surface shadow-resting transition hover:shadow-raised sm:flex-row"
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

                <h3 className="mt-1 font-display text-lg font-semibold text-text">
                    {vehicle.make} {vehicle.model}
                </h3>

                <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-textMuted">
                    {vehicle.transmission_type && (
                        <span className="inline-flex items-center gap-1">
                            <Cog className="h-3.5 w-3.5" />
                            {capitalize(vehicle.transmission_type)}
                        </span>
                    )}
                    {vehicle.seat_count > 0 && (
                        <span className="inline-flex items-center gap-1">
                            <Users className="h-3.5 w-3.5" />
                            {vehicle.seat_count} sièges
                        </span>
                    )}
                    {vehicle.fuel_type && (
                        <span className="inline-flex items-center gap-1">
                            <Gauge className="h-3.5 w-3.5" />
                            {capitalize(vehicle.fuel_type)}
                        </span>
                    )}
                </div>

                <p className="mt-3 font-display text-xl font-bold text-text">
                    {vehicle.daily_rate} DH / jour
                </p>

                <span className="mt-3 w-full rounded-interactive bg-primary py-2.5 text-center text-sm font-semibold text-onPrimary group-hover:bg-primaryHover">
                    Réserver maintenant
                </span>
            </div>
        </Link>
    );
}
