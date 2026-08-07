import CookieBanner from '@/Components/CookieBanner';
import SiteLogo from '@/Components/SiteLogo';
import { PageProps } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, useState } from 'react';

/**
 * The one real, fixed customer-facing layout — deliberately not a
 * LayoutVariantRegistry region (see docs/event-registry.md's "Layout
 * Variant Regions" section: that mechanism doesn't exist in this project,
 * and one real client theme doesn't need swappable layouts).
 *
 * Structure/spacing/interaction pattern (sticky blurred header, dark
 * multi-column footer) is drawn from the real Stitch "Premium Mobility
 * Design System" HTML export (Accueil / Nos Véhicules screens) — but
 * every color/spacing/radius value goes through this project's own theme
 * tokens (Hard Rule 3), and every nav item is a real link to a page that
 * actually exists here, not a copy of Stitch's marketing-site nav
 * (Services/À propos/FAQ have no corresponding real page in this app).
 *
 * The header carries a FR|EN locale switcher: it re-issues the current URL
 * with a `?lang=` param (preserving any existing query — search, filters,
 * etc.), which HandleInertiaRequests resolves into the shared `locale` prop
 * the useTranslation hook keys off.
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    // `auth` defaults to a logged-out shape because custom error pages
    // (Errors/NotFound, Errors/ServerError) are rendered from the exception
    // handler for UNMATCHED routes — a request that matched no route never
    // runs the HandleInertiaRequests middleware, so the shared `auth` prop
    // is simply absent there. The layout must not crash on its absence.
    const { auth = { user: null }, driverVerificationStatus, siteIdentity, locale } = usePage<
        PageProps & { siteIdentity?: { siteName?: string }; locale?: string }
    >().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const { t } = useTranslation();
    const currentLocale = locale ?? 'fr';

    const needsDriverVerification =
        auth.user !== null && driverVerificationStatus !== null && driverVerificationStatus !== 'approved';

    const switchLocale = (lang: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', lang);
        router.get(url.pathname + url.search, {}, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const localeSwitcher = (
        <div
            className="flex items-center rounded-pill border border-border p-0.5 text-xs font-semibold"
            role="group"
            aria-label="Language"
        >
            {(['fr', 'en'] as const).map((code) => (
                <button
                    key={code}
                    type="button"
                    onClick={() => switchLocale(code)}
                    aria-pressed={currentLocale === code}
                    className={`rounded-pill px-2 py-1 uppercase transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing ${
                        currentLocale === code
                            ? 'bg-primary text-onPrimary'
                            : 'text-textMuted hover:text-text'
                    }`}
                >
                    {code}
                </button>
            ))}
        </div>
    );

    return (
        <div className="flex min-h-screen flex-col bg-background font-body text-text">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-interactive focus:bg-primary focus:px-4 focus:py-2 focus:text-onPrimary"
            >
                {t('Skip to content')}
            </a>

            <header className="sticky top-0 z-50 border-b border-border bg-surface/90 backdrop-blur-md">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <Link href="/">
                        <SiteLogo />
                    </Link>

                    <nav className="hidden items-center gap-8 md:flex">
                        <Link href={route('vehicles.index')} className="text-sm font-semibold text-textMuted transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                            {t('Our Fleet')}
                        </Link>

                        {auth.user ? (
                            <>
                                <Link href={route('profile.edit')} className="text-sm font-semibold text-textMuted transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                    {t('My Account')}
                                </Link>
                                {needsDriverVerification && (
                                    <Link
                                        href={route('driver-verification.show')}
                                        className="text-sm font-semibold text-warning transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                                    >
                                        {t('Driver Verification')}
                                    </Link>
                                )}
                                <Link href={route('logout')} method="post" as="button" className="text-sm font-semibold text-textMuted transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                    {t('Log Out')}
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className="text-sm font-semibold text-textMuted transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                    {t('Log In')}
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-interactive bg-primary px-4 py-2 text-sm font-semibold text-onPrimary transition-colors hover:bg-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                                >
                                    {t('Sign Up')}
                                </Link>
                            </>
                        )}

                        {localeSwitcher}
                    </nav>

                    <button
                        type="button"
                        className="rounded-interactive text-text md:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                        onClick={() => setMobileMenuOpen((open) => !open)}
                        aria-label={t('Toggle menu')}
                    >
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                {mobileMenuOpen && (
                    <nav className="flex flex-col gap-1 border-t border-border bg-surface px-4 py-3 md:hidden">
                        <Link href={route('vehicles.index')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                            {t('Our Fleet')}
                        </Link>
                        {auth.user ? (
                            <>
                                <Link href={route('profile.edit')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                    {t('My Account')}
                                </Link>
                                {needsDriverVerification && (
                                    <Link href={route('driver-verification.show')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-warning hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                        {t('Driver Verification')}
                                    </Link>
                                )}
                                <Link href={route('logout')} method="post" as="button" className="rounded-interactive px-2 py-2 text-left text-sm font-semibold text-textMuted hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                    {t('Log Out')}
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                    {t('Log In')}
                                </Link>
                                <Link href={route('register')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                    {t('Sign Up')}
                                </Link>
                            </>
                        )}
                        <div className="pt-2">{localeSwitcher}</div>
                    </nav>
                )}
            </header>

            <main id="main-content" className="flex-grow">{children}</main>

            <footer className="mt-auto grid grid-cols-1 gap-8 bg-primary px-4 py-16 text-onPrimary sm:px-6 md:grid-cols-4 lg:px-8">
                <div className="space-y-4">
                    <SiteLogo textClassName="text-onPrimary" iconClassName="text-onPrimary" />
                    <p className="text-sm text-onPrimary/80">{t('Your trusted partner for premium, hassle-free mobility.')}</p>
                </div>

                <div className="flex flex-col gap-3">
                    <span className="text-sm font-semibold">{t('Browse')}</span>
                    <Link href={route('vehicles.index')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-onPrimary">
                        {t('Our Fleet')}
                    </Link>
                </div>

                <div className="flex flex-col gap-3">
                    <span className="text-sm font-semibold">{t('Account')}</span>
                    <Link href={route('bookings.track')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-onPrimary">
                        {t('Track your booking')}
                    </Link>
                    {auth.user ? (
                        <Link href={route('profile.edit')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-onPrimary">
                            {t('My Account')}
                        </Link>
                    ) : (
                        <>
                            <Link href={route('login')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-onPrimary">
                                {t('Log In')}
                            </Link>
                            <Link href={route('register')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-onPrimary">
                                {t('Sign Up')}
                            </Link>
                        </>
                    )}
                </div>

                <div className="flex flex-col justify-end text-sm text-onPrimary/80 md:text-right">
                    © {new Date().getFullYear()} {siteIdentity?.siteName ?? 'Car Rental'}. {t('All rights reserved.')}
                </div>
            </footer>

            {/* Cookie consent banner — fixed bottom, shown on every
                storefront page until the visitor accepts (localStorage). */}
            <CookieBanner />
        </div>
    );
}
