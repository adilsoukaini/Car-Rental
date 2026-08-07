import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import Text from '@/Components/Text';
import { Vehicle } from '@/types';
import { Link } from '@inertiajs/react';
import { Cog, Gauge, Users } from 'lucide-react';

/**
 * Image on top, details below — matches the Stitch design source's "Nos
 * Véhicules - Project Atlas"/"Sidebar Search" card structure: a full-bleed
 * image (category badge overlaid on a subtle photo scrim), the name,
 * feature chips (transmission/fuel/seats), price, and a full-width CTA
 * spanning the card footer.
 *
 * The whole card is a single Link (matches this project's existing
 * click-through-to-detail UX) — the CTA is a styled span, not a nested
 * <button>/<a>, to avoid invalid interactive-element nesting.
 */

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

const chipClassName =
    'inline-flex items-center gap-1 rounded-pill border border-border bg-background px-2 py-0.5 text-xs font-medium text-textMuted';

export default function Vertical({ vehicle }: { vehicle: Vehicle }) {
    return (
        <Link
            href={route('vehicles.show', vehicle.id)}
            className="group flex flex-col overflow-hidden rounded-container border border-border bg-surface shadow-resting transition hover:shadow-raised"
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

                <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-photoScrim/60 via-photoScrim/10 to-transparent" />

                <span className="absolute bottom-3 left-3 rounded-pill bg-surface/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-text">
                    {vehicle.category}
                </span>
            </div>

            <div className="flex flex-1 flex-col gap-3 p-4">
                <h3 className="font-display text-xl font-bold text-text">
                    {vehicle.make} {vehicle.model}
                </h3>

                <div className="flex flex-wrap gap-2">
                    <span className={chipClassName}>
                        <Cog className="h-3.5 w-3.5" />
                        {capitalize(vehicle.transmission_type)}
                    </span>
                    <span className={chipClassName}>
                        <Gauge className="h-3.5 w-3.5" />
                        {capitalize(vehicle.fuel_type)}
                    </span>
                    <span className={chipClassName}>
                        <Users className="h-3.5 w-3.5" />
                        {vehicle.seat_count} seats
                    </span>
                </div>

                <div className="mt-auto flex items-baseline gap-1 pt-2">
                    <Text variant="mono-price">{vehicle.daily_rate}</Text>
                    <span className="text-sm font-normal text-textMuted">MAD / day</span>
                </div>

                <span className="w-full rounded-interactive bg-primary py-2 text-center font-body text-sm font-semibold text-onPrimary transition group-hover:bg-primaryHover">
                    View Details
                </span>
            </div>
        </Link>
    );
}
