import PublicLayout from '@/Layouts/PublicLayout';
import { LayoutSlot } from '@/layoutComponentRegistry';
import { PageProps, Vehicle } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Calendar, Car, FileText, Hand, Headphones,
    Lock, MapPin, Search, Shield, Star, Users,
} from 'lucide-react';
import { useState } from 'react';

const features = [
    { icon: Hand, title: 'Easy booking', description: 'Pick your vehicle and confirm your reservation in just a few clicks, hassle-free.' },
    { icon: Lock, title: 'Secure payment', description: 'Protected, transparent transactions with no hidden fees.' },
    { icon: FileText, title: 'Digital contract', description: 'No more paperwork. Sign your contract right from your smartphone.' },
    { icon: Car, title: 'Recent vehicles', description: 'A constantly renewed fleet for your comfort and safety.' },
] as const;

const stats = [
    { icon: Star, value: '+5000', label: 'Satisfied customers' },
    { icon: Car, value: '+100', label: 'Vehicles available' },
    { icon: Headphones, value: '24/7', label: 'Support' },
    { icon: Shield, value: 'Best prices', label: 'Guarantee' },
] as const;

interface HomepageContentProps {
    hero_title?: string | null;
    hero_subtitle?: string | null;
    hero_cta_text?: string | null;
    hero_cta_link?: string | null;
    features_title?: string | null;
    features_subtitle?: string | null;
    cta_band_title?: string | null;
    cta_band_subtitle?: string | null;
}

export default function Home({
    featuredVehicles,
    homepageContent,
    locations = [],
}: {
    featuredVehicles: Vehicle[];
    homepageContent?: HomepageContentProps;
    locations?: { id: number; name: string; city: string }[];
}) {
    const { t } = useTranslation();
    const today = new Date().toISOString().slice(0, 10);
    const [locationQuery, setLocationQuery] = useState('');
    const [pickupDate, setPickupDate] = useState('');
    const [returnDate, setReturnDate] = useState('');

    const heroTitle = homepageContent?.hero_title ?? t('Excellence in car rental.');
    const heroSubtitle = homepageContent?.hero_subtitle ?? t('Discover a premium fleet for uncompromising travel. Fast booking, immaculate vehicles, impeccable service.');
    const heroCtaText = homepageContent?.hero_cta_text ?? t('Find a vehicle');
    const featuresTitle = homepageContent?.features_title ?? t('Why choose Project Atlas?');
    const featuresSubtitle = homepageContent?.features_subtitle ?? t("We're redefining car rental with streamlined processes and a carefully maintained fleet.");
    const ctaBandTitle = homepageContent?.cta_band_title ?? t('Ready for adventure?');
    const ctaBandSubtitle = homepageContent?.cta_band_subtitle ?? t('Book now and enjoy Morocco with total freedom.');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (locationQuery) params.set('location', locationQuery);
        if (pickupDate) params.set('pickup', pickupDate);
        if (returnDate) params.set('return', returnDate);
        window.location.href = route('vehicles.index') + (params.toString() ? '?' + params.toString() : '');
    };

    return (
        <PublicLayout>
            <Head title={t('Home')} />

            <section className="bg-primary px-4 pb-24 pt-20 sm:px-6 lg:px-8 lg:pt-28">
                <div className="mx-auto max-w-7xl">
                    <div className="grid items-center gap-12 lg:grid-cols-2">
                        <div className="max-w-3xl">
                            <span className="mb-4 inline-block rounded-pill bg-onPrimary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-onPrimary">
                                {t('NEW STANDARD')}
                            </span>
                            <h1 className="font-display text-4xl font-bold leading-tight text-onPrimary sm:text-5xl lg:text-6xl">
                                {heroTitle}
                            </h1>
                            <p className="mt-5 max-w-xl text-lg text-onPrimary/80">
                                {heroSubtitle}
                            </p>
                        </div>
                        <div className="hidden aspect-[4/3] items-center justify-center rounded-container bg-onPrimary/10 lg:flex">
                            <Car className="h-24 w-24 text-onPrimary/40" />
                        </div>
                    </div>
                </div>
            </section>

            {/* Functional booking/search card */}
            <div className="relative z-10 mx-auto -mt-12 max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="rounded-container bg-surface p-6 shadow-overlay">
                    <form onSubmit={handleSearch}>
                        <div className="grid grid-cols-1 items-end gap-4 md:grid-cols-[1fr_1fr_1fr_auto]">
                            <div className="flex flex-col gap-1">
                                <label htmlFor="home-location" className="text-xs font-semibold text-textMuted">
                                    {t('Pickup location')}
                                </label>
                                <div className="relative">
                                    <MapPin className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
                                    <input id="home-location" type="text" list="home-location-list"
                                        value={locationQuery} onChange={(e) => setLocationQuery(e.target.value)}
                                        placeholder={t('City or airport')}
                                        className="w-full rounded-interactive border border-border bg-background py-2.5 pl-9 pr-3 text-sm text-text placeholder:text-textMuted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                    />
                                    <datalist id="home-location-list">
                                        {locations.map((loc) => (<option key={loc.id} value={loc.city} />))}
                                    </datalist>
                                </div>
                            </div>
                            <div className="flex flex-col gap-1">
                                <label htmlFor="home-pickup" className="text-xs font-semibold text-textMuted">{t('Pickup date')}</label>
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
                                    <input id="home-pickup" type="date" value={pickupDate}
                                        onChange={(e) => setPickupDate(e.target.value)} min={today}
                                        className="w-full rounded-interactive border border-border bg-background py-2.5 pl-9 pr-3 text-sm text-text focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col gap-1">
                                <label htmlFor="home-return" className="text-xs font-semibold text-textMuted">{t('Return date')}</label>
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
                                    <input id="home-return" type="date" value={returnDate}
                                        onChange={(e) => setReturnDate(e.target.value)} min={pickupDate || today}
                                        className="w-full rounded-interactive border border-border bg-background py-2.5 pl-9 pr-3 text-sm text-text focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                    />
                                </div>
                            </div>
                            <button type="submit"
                                className="flex items-center justify-center gap-2 rounded-interactive bg-secondary px-6 py-2.5 font-semibold text-onSecondary transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                            >
                                <Search className="h-5 w-5" aria-hidden="true" />
                                {heroCtaText}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {/* Value props */}
            <section className="bg-surface px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <h2 className="text-center font-display text-3xl font-bold text-text">{featuresTitle}</h2>
                    <p className="mx-auto mt-4 max-w-2xl text-center text-textMuted">{featuresSubtitle}</p>
                    <div className="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((f) => (
                            <div key={f.title} className="flex flex-col items-center text-center">
                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                                    <f.icon className="h-6 w-6 text-primary" aria-hidden="true" />
                                </div>
                                <h3 className="mt-4 font-display text-lg font-semibold text-text">{t(f.title)}</h3>
                                <p className="mt-2 text-sm text-textMuted">{t(f.description)}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Featured vehicles */}
            <section className="px-4 py-20 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="flex items-end justify-between">
                        <div>
                            <h2 className="font-display text-3xl font-bold text-text">{t('Our selection of vehicles')}</h2>
                            <p className="mt-2 text-textMuted">{t('Models suited to every mobility need.')}</p>
                        </div>
                        <Link href={route('vehicles.index')} className="text-sm font-semibold text-primary hover:underline">
                            {t('Browse all vehicles')} &rarr;
                        </Link>
                    </div>
                    <div className="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {featuredVehicles.slice(0, 4).map((v) => (
                            <LayoutSlot key={v.id} name="vehicleCard" vehicle={v} />
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA band */}
            <section className="bg-primary px-4 py-16 text-center sm:px-6 lg:px-8">
                <h2 className="font-display text-3xl font-bold text-onPrimary">{ctaBandTitle}</h2>
                <p className="mt-3 text-onPrimary/80">{ctaBandSubtitle}</p>
                <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                    <Link href={route('vehicles.index')}
                        className="rounded-interactive border border-onPrimary/30 px-6 py-3 font-semibold text-onPrimary transition hover:bg-onPrimary/10">
                        {t('Discover our vehicles')}
                    </Link>
                    <Link href={route('vehicles.index')}
                        className="rounded-interactive bg-secondary px-6 py-3 font-semibold text-onSecondary transition hover:opacity-90">
                        {t('Book now')}
                    </Link>
                </div>
            </section>

            {/* Stats bar */}
            <section className="bg-surface px-4 py-12 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="grid grid-cols-2 gap-8 text-center sm:grid-cols-4">
                        {stats.map((s) => (
                            <div key={s.label}>
                                <s.icon className="mx-auto h-6 w-6 text-primary" aria-hidden="true" />
                                <p className="mt-2 font-display text-2xl font-bold text-text">{s.value}</p>
                                <p className="text-sm text-textMuted">{t(s.label)}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
