import { Paginated, Vehicle } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Index({ vehicles }: { vehicles: Paginated<Vehicle> }) {
    return (
        <div className="min-h-screen bg-background p-8 font-body text-text">
            <Head title="Our Fleet" />

            <h1 className="mb-8 font-display text-3xl font-bold text-text">
                Our Fleet
            </h1>

            {vehicles.data.length === 0 ? (
                <p className="text-textMuted">No vehicles available right now.</p>
            ) : (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {vehicles.data.map((vehicle) => (
                        <Link
                            key={vehicle.id}
                            href={route('vehicles.show', vehicle.id)}
                            className="rounded-container border border-border bg-surface p-6 shadow-resting transition hover:shadow-raised"
                        >
                            <h2 className="mb-1 font-display text-xl font-semibold text-text">
                                {vehicle.make} {vehicle.model}
                            </h2>
                            <p className="mb-3 text-sm text-textMuted">
                                {vehicle.year} · {vehicle.category} · {vehicle.seat_count} seats ·{' '}
                                {vehicle.transmission_type}
                            </p>
                            {vehicle.location && (
                                <p className="mb-3 text-sm text-textMuted">
                                    {vehicle.location.city}
                                </p>
                            )}
                            <p className="font-mono text-lg font-semibold text-text">
                                {vehicle.daily_rate} MAD / day
                            </p>
                        </Link>
                    ))}
                </div>
            )}

            {vehicles.last_page > 1 && (
                <nav className="mt-8 flex gap-2">
                    {vehicles.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            className={`rounded-interactive px-3 py-1 text-sm ${
                                link.active
                                    ? 'bg-primary text-onPrimary'
                                    : 'border border-border text-textMuted'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </div>
    );
}
