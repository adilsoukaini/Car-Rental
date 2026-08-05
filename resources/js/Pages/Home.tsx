import PublicLayout from '@/Layouts/PublicLayout';
import { Vehicle } from '@/types';
import { Head, Link } from '@inertiajs/react';

/**
 * Replaces Laravel's default Welcome scaffold at "/" — real reachability-
 * audit finding #3. Hero + featured-vehicles structure is drawn from the
 * real Stitch "Accueil - Project Atlas" homepage screen (see
 * docs/05-FRONTEND-FOUNDATION-PHASE.md's Task 4), simplified to what this
 * app actually has real data for today — no standalone "Services"/
 * "À propos" marketing sections invented for pages that don't exist.
 */
export default function Home({ featuredVehicles }: { featuredVehicles: Vehicle[] }) {
    return (
        <PublicLayout>
            <Head title="Welcome" />

            <section className="bg-surface px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-3xl text-center">
                    <span className="mb-4 inline-block rounded-pill bg-primary/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary">
                        Premium mobility
                    </span>
                    <h1 className="font-display text-4xl font-extrabold text-text sm:text-5xl">
                        Rent your next car, hassle-free.
                    </h1>
                    <p className="mx-auto mt-4 max-w-xl text-lg text-textMuted">
                        A premium fleet for travel without compromise. Fast booking, immaculate vehicles,
                        impeccable service.
                    </p>
                    <div className="mt-8">
                        <Link
                            href={route('vehicles.index')}
                            className="inline-block rounded-interactive bg-primary px-8 py-3 font-semibold text-onPrimary shadow-resting transition-colors hover:bg-primaryHover"
                        >
                            Browse our fleet
                        </Link>
                    </div>
                </div>
            </section>

            <section className="bg-background px-4 py-16 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="mb-10 flex items-end justify-between">
                        <h2 className="font-display text-2xl font-bold text-text">Featured vehicles</h2>
                        <Link href={route('vehicles.index')} className="text-sm font-semibold text-primary hover:underline">
                            See the full fleet &rarr;
                        </Link>
                    </div>

                    {featuredVehicles.length === 0 ? (
                        <p className="text-textMuted">No vehicles available right now.</p>
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {featuredVehicles.map((vehicle) => (
                                <Link
                                    key={vehicle.id}
                                    href={route('vehicles.show', vehicle.id)}
                                    className="rounded-container border border-border bg-surface p-6 shadow-resting transition hover:shadow-raised"
                                >
                                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-textMuted">
                                        {vehicle.category}
                                    </span>
                                    <h3 className="mb-3 font-display text-lg font-semibold text-text">
                                        {vehicle.make} {vehicle.model}
                                    </h3>
                                    <p className="font-mono text-lg font-semibold text-text">
                                        {vehicle.daily_rate} MAD / day
                                    </p>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </section>
        </PublicLayout>
    );
}
