import PublicLayout from '@/Layouts/PublicLayout';
import { LayoutSlot } from '@/layoutComponentRegistry';
import { Vehicle } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    Car,
    FileText,
    Hand,
    Headphones,
    Lock,
    MapPin,
    Search,
    Shield,
    Star,
    Users,
} from 'lucide-react';

/**
 * Replaces Laravel's default Welcome scaffold at "/" — real reachability-
 * audit finding #3. A faithful replication of the Stitch "Premium Mobility
 * Design System" homepage (Accueil - Project Atlas): navy hero with a split
 * text/visual layout, an elevated white booking card straddling its bottom
 * edge, four value props, a featured-vehicles grid, a CTA banner, and a
 * stats bar. Every color/spacing/radius value goes through the theme token
 * system (Hard Rule 3) — no hardcoded palette.
 *
 * The booking card is visual only: both fields and the CTA are links to the
 * real fleet page, so the homepage reads like a real booking experience
 * without inventing a form that submits nowhere.
 */
const features = [
    {
        icon: Hand,
        title: 'Réservation facile',
        description:
            'En quelques clics, choisissez votre véhicule et validez votre réservation sans tracas.',
    },
    {
        icon: Lock,
        title: 'Paiement sécurisé',
        description: 'Des transactions protégées et transparentes, sans frais cachés.',
    },
    {
        icon: FileText,
        title: 'Contrat digital',
        description: 'Fini la paperasse. Signez votre contrat directement sur votre smartphone.',
    },
    {
        icon: Car,
        title: 'Véhicules récents',
        description: 'Une flotte constamment renouvelée pour votre confort et votre sécurité.',
    },
] as const;

const stats = [
    { icon: Star, value: '+5000', label: 'Clients satisfaits' },
    { icon: Car, value: '+100', label: 'Véhicules disponibles' },
    { icon: Headphones, value: '24/7', label: 'Assistance' },
    { icon: Shield, value: 'Meilleurs prix', label: 'Garantie' },
] as const;

export default function Home({ featuredVehicles }: { featuredVehicles: Vehicle[] }) {
    return (
        <PublicLayout>
            <Head title="Accueil" />

            {/* Hero — full-width navy band with a split text/visual layout; the
                booking card below straddles its bottom edge. */}
            <section className="bg-primary px-4 pb-24 pt-20 sm:px-6 lg:px-8 lg:pt-28">
                <div className="mx-auto max-w-7xl">
                    <div className="grid items-center gap-12 lg:grid-cols-2">
                        <div className="max-w-3xl">
                            <span className="mb-4 inline-block rounded-pill bg-onPrimary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-onPrimary">
                                NOUVEAU STANDARD
                            </span>
                            <h1 className="font-display text-4xl font-bold leading-tight text-onPrimary sm:text-5xl lg:text-6xl">
                                L&rsquo;excellence de la location de voitures.
                            </h1>
                            <p className="mt-5 max-w-xl text-lg text-onPrimary/80">
                                Découvrez une flotte premium pour des voyages sans compromis. Réservation
                                rapide, véhicules impeccables, service irréprochable.
                            </p>
                        </div>

                        {/* Visual placeholder — hero images aren't available yet,
                            so the split is carried by a themed panel. */}
                        <div className="hidden aspect-[4/3] items-center justify-center rounded-container bg-onPrimary/10 lg:flex">
                            <Car className="h-24 w-24 text-onPrimary/40" />
                        </div>
                    </div>
                </div>
            </section>

            {/* Booking card — visual only; both fields and the CTA link to the fleet page. */}
            <div className="relative z-10 mx-auto -mt-12 max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="rounded-container bg-surface p-6 shadow-overlay">
                    <div className="grid grid-cols-1 items-center gap-4 md:grid-cols-[1fr_1fr_auto]">
                        <Link
                            href={route('vehicles.index')}
                            className="flex items-center gap-3 rounded-interactive border border-border bg-background px-4 py-3 transition-colors hover:border-primary"
                        >
                            <MapPin className="h-5 w-5 shrink-0 text-primary" />
                            <span className="text-sm font-medium text-text">Lieu de prise en charge</span>
                        </Link>

                        <Link
                            href={route('vehicles.index')}
                            className="flex items-center gap-3 rounded-interactive border border-border bg-background px-4 py-3 transition-colors hover:border-primary"
                        >
                            <Calendar className="h-5 w-5 shrink-0 text-primary" />
                            <span className="text-sm font-medium text-text">Date de prise en charge</span>
                        </Link>

                        <Link
                            href={route('vehicles.index')}
                            className="flex items-center justify-center gap-2 rounded-interactive bg-secondary px-6 py-3 font-semibold text-onSecondary transition-opacity hover:opacity-90"
                        >
                            <Search className="h-5 w-5" />
                            Trouver un véhicule
                        </Link>
                    </div>
                </div>
            </div>

            {/* Value props */}
            <section className="bg-surface px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="mx-auto mb-12 max-w-2xl text-center">
                        <h2 className="font-display text-3xl font-bold text-text">
                            Pourquoi choisir Project Atlas ?
                        </h2>
                        <p className="mt-2 text-lg text-textMuted">
                            Nous redéfinissons la location de voitures avec des processus simplifiés et
                            une flotte soigneusement entretenue.
                        </p>
                    </div>

                    <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map(({ icon: Icon, title, description }) => (
                            <div key={title} className="flex flex-col items-center text-center">
                                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <Icon className="h-7 w-7" />
                                </div>
                                <h3 className="mt-4 font-display text-xl font-semibold text-text">
                                    {title}
                                </h3>
                                <p className="mt-2 text-sm leading-relaxed text-textMuted">
                                    {description}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Featured vehicles */}
            <section className="bg-background px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="mb-10 flex items-end justify-between gap-4">
                        <div>
                            <h2 className="font-display text-3xl font-bold text-text">
                                Notre sélection de véhicules
                            </h2>
                            <p className="mt-2 text-textMuted">
                                Des modèles adaptés à chaque besoin de mobilité.
                            </p>
                        </div>
                        <Link
                            href={route('vehicles.index')}
                            className="whitespace-nowrap text-sm font-semibold text-primary hover:underline"
                        >
                            Voir tout le catalogue &rarr;
                        </Link>
                    </div>

                    {featuredVehicles.length === 0 ? (
                        <p className="text-textMuted">Aucun véhicule disponible pour le moment.</p>
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {featuredVehicles.slice(0, 4).map((vehicle) => (
                                <LayoutSlot key={vehicle.id} name="vehicleCard" vehicle={vehicle} />
                            ))}
                        </div>
                    )}
                </div>
            </section>

            {/* CTA banner */}
            <section className="bg-primary px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl text-center">
                    <h2 className="font-display text-3xl font-bold text-onPrimary sm:text-4xl">
                        Prêt pour l&rsquo;aventure ?
                    </h2>
                    <p className="mx-auto mt-3 max-w-xl text-lg text-onPrimary/80">
                        Réservez dès maintenant et profitez du Maroc en toute liberté.
                    </p>
                    <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            href={route('vehicles.index')}
                            className="rounded-interactive border border-onPrimary/30 px-6 py-3 font-semibold text-onPrimary transition-colors hover:bg-onPrimary/10"
                        >
                            Découvrir nos véhicules
                        </Link>
                        <Link
                            href={route('vehicles.index')}
                            className="rounded-interactive bg-secondary px-6 py-3 font-semibold text-onSecondary transition-opacity hover:opacity-90"
                        >
                            Réserver maintenant
                        </Link>
                    </div>
                </div>
            </section>

            {/* Stats bar */}
            <section className="bg-background px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
                        {stats.map(({ icon: Icon, value, label }) => (
                            <div key={label} className="flex flex-col items-center text-center">
                                <Icon className="h-8 w-8 text-primary" />
                                <span className="mt-3 font-display text-4xl font-bold text-text">
                                    {value}
                                </span>
                                <span className="mt-1 text-sm font-medium text-textMuted">{label}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
