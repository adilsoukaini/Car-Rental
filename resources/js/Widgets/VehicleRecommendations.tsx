import VehiclePlaceholderIcon from '@/Components/VehiclePlaceholderIcon';
import { Link } from '@inertiajs/react';

/**
 * A single recommendation card shape, as resolved by the
 * vehicle.recommendations filter (GetVehicleRecommendationsPipe).
 */
export interface VehicleRecommendation {
    id: number;
    make: string;
    model: string;
    category: string;
    daily_rate: string;
    imageUrl: string | null;
}

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

/**
 * "You might also like" — similar vehicles rendered as a horizontal row of
 * up to 4 cards on desktop, stacking on mobile. Rendered on the vehicle
 * detail page below the reviews section. The whole card is a single Link
 * (same click-through-to-detail UX as the fleet vehicle cards); no nested
 * interactive elements. Colors/spacing come exclusively from theme tokens.
 */
export default function VehicleRecommendations({ vehicles }: { vehicles: VehicleRecommendation[] }) {
    if (!vehicles || vehicles.length === 0) {
        return null;
    }

    return (
        <section className="mt-8">
            <h2 className="font-display text-lg font-medium text-text">You might also like</h2>

            <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {vehicles.map((v) => (
                    <Link
                        key={v.id}
                        href={route('vehicles.show', v.id)}
                        className="group flex flex-col overflow-hidden rounded-container border border-border bg-surface shadow-resting transition hover:shadow-raised"
                    >
                        <div className="relative h-36 w-full overflow-hidden bg-background">
                            {v.imageUrl ? (
                                <img
                                    src={v.imageUrl}
                                    alt={`${v.make} ${v.model}`}
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
                                {capitalize(v.category)}
                            </p>

                            <h3 className="mt-1 font-display text-base font-semibold text-text">
                                {v.make} {v.model}
                            </h3>

                            <p className="mt-2 font-display text-lg font-bold text-text">
                                {String(Number(v.daily_rate))}{' '}
                                <span className="text-xs font-semibold text-textMuted">DH / jour</span>
                            </p>
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}
