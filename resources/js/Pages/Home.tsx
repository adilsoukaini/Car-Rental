import PublicLayout from '@/Layouts/PublicLayout';
import { LayoutSlot } from '@/layoutComponentRegistry';
import { Vehicle } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Calendar, Car, Headphones, MapPin, Search, Shield } from 'lucide-react';

/**
 * Replaces Laravel's default Welcome scaffold at "/" — real reachability-
 * audit finding #3. Redesigned to match the Stitch "Premium Mobility
 * Design System" homepage structure (Accueil - Project Atlas): navy hero
 * with a booking card straddling its bottom edge, a featured-vehicles grid,
 * and a three-column trust section. Every color/spacing/radius value goes
 * through the theme token system (Hard Rule 3) — no hardcoded palette.
 *
 * The booking card is visual only: both fields and the CTA are links to the
 * real fleet page, so the homepage reads like a real booking experience
 * without inventing a form that submits nowhere.
 */
const features = [
    {
        icon: Car,
        title: 'Wide Selection',
        description:
            'From compact city cars to premium SUVs — a fleet built for every journey, all in immaculate condition.',
    },
    {
        icon: Shield,
        title: 'Best Prices',
        description:
            'Transparent daily rates with no hidden fees, plus discounts that grow the longer you rent.',
    },
    {
        icon: Headphones,
        title: '24/7 Support',
        description:
            'Around-the-clock assistance for bookings, returns, and anything else that comes up on the road.',
    },
] as const;

export default function Home({ featuredVehicles }: { featuredVehicles: Vehicle[] }) {
    return (
        <PublicLayout>
            <Head title="Home" />

            {/* Hero — full-width navy band; the booking card below straddles its bottom edge. */}
            <section className="bg-primary px-4 pb-24 pt-20 sm:px-6 lg:px-8 lg:pt-28">
                <div className="mx-auto max-w-7xl">
                    <div className="max-w-3xl">
                        <span className="mb-4 inline-block rounded-pill bg-onPrimary/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-onPrimary">
                            Premium mobility
                        </span>
                        <h1 className="font-display text-4xl font-bold leading-tight text-onPrimary sm:text-5xl lg:text-6xl">
                            Premium Car Rental
                        </h1>
                        <p className="mt-5 max-w-xl text-lg text-onPrimary/80">
                            A premium fleet for travel without compromise. Fast booking, immaculate vehicles,
                            impeccable service.
                        </p>
                    </div>
                </div>
            </section>

            {/* Booking card — visual only; both fields and the CTA link to the fleet page. */}
            <div className="relative z-10 mx-auto -mt-16 max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="rounded-container border border-border bg-surface p-6 shadow-overlay sm:p-8">
                    <h2 className="mb-5 font-display text-xl font-semibold text-text">Book Your Vehicle</h2>

                    <div className="grid items-stretch gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Link
                            href={route('vehicles.index')}
                            className="flex flex-col gap-1 rounded-interactive border border-border bg-background px-4 py-3 transition-colors hover:border-primary"
                        >
                            <span className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-textMuted">
                                <MapPin className="h-4 w-4 text-primary" />
                                Pickup Location
                            </span>
                            <span className="text-sm text-text">Select your location</span>
                        </Link>

                        <Link
                            href={route('vehicles.index')}
                            className="flex flex-col gap-1 rounded-interactive border border-border bg-background px-4 py-3 transition-colors hover:border-primary"
                        >
                            <span className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-textMuted">
                                <Calendar className="h-4 w-4 text-primary" />
                                Pickup Date
                            </span>
                            <span className="text-sm text-text">Choose your dates</span>
                        </Link>

                        <Link
                            href={route('vehicles.index')}
                            className="flex items-center justify-center gap-2 rounded-interactive bg-secondary px-6 py-3 font-semibold text-onSecondary transition-opacity hover:opacity-90 md:col-span-2 lg:col-span-1"
                        >
                            <Search className="h-5 w-5" />
                            Search Vehicles
                        </Link>
                    </div>
                </div>
            </div>

            {/* Featured vehicles */}
            <section className="bg-background px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="mb-10 flex items-end justify-between gap-4">
                        <div>
                            <h2 className="font-display text-3xl font-bold text-text">Featured Vehicles</h2>
                            <p className="mt-2 text-textMuted">Browse our most popular rentals</p>
                        </div>
                        <Link
                            href={route('vehicles.index')}
                            className="whitespace-nowrap text-sm font-semibold text-primary hover:underline"
                        >
                            See the full fleet &rarr;
                        </Link>
                    </div>

                    {featuredVehicles.length === 0 ? (
                        <p className="text-textMuted">No vehicles available right now.</p>
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {featuredVehicles.map((vehicle) => (
                                <LayoutSlot key={vehicle.id} name="vehicleCard" vehicle={vehicle} />
                            ))}
                        </div>
                    )}
                </div>
            </section>

            {/* Why choose us */}
            <section className="bg-surface px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="mx-auto mb-12 max-w-2xl text-center">
                        <h2 className="font-display text-3xl font-bold text-text">Why Choose Us</h2>
                        <p className="mt-2 text-lg text-textMuted">A premium rental experience, simplified.</p>
                    </div>

                    <div className="grid gap-10 sm:grid-cols-3">
                        {features.map(({ icon: Icon, title, description }) => (
                            <div key={title} className="flex flex-col items-center text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-container bg-primary/10 text-primary">
                                    <Icon className="h-7 w-7" />
                                </div>
                                <h3 className="mt-4 font-display text-xl font-semibold text-text">{title}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-textMuted">{description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
