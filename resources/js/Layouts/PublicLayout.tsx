import CookieBanner from '@/Components/CookieBanner';
import CurrencySelector from '@/Components/CurrencySelector';
import NotificationBanner from '@/Components/NotificationBanner';
import SiteLogo from '@/Components/SiteLogo';
import { PageProps } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Link, router, usePage } from '@inertiajs/react';
import { Menu, Transition } from '@headlessui/react';
import { CarFront, IdCard, LogOut, MessageCircle, User } from 'lucide-react';
import { PropsWithChildren, useEffect, useState } from 'react';

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
 *
 * Auth pattern (competitor-informed — Hertz/Sixt/DiscoverCars all collapse
 * account actions behind one trigger while keeping "Manage my booking" a
 * visible link): authenticated users get a circular-avatar Headless UI Menu
 * (user initial, else a User icon) holding My Reservations, pre-verification,
 * the FR|EN switcher, the currency display and Sign Out. Guests keep the
 * Log In / Sign Up links plus the language switcher. On mobile everything
 * auth-related lives in the hamburger menu.
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
    // One-time, per-user dismissible "complete your profile" nudge — shown
    // below the header only while a logged-in user has never submitted a
    // driver verification (status 'none'). Reads dismissal from localStorage
    // (keyed by user id) so SSR and the first paint are safe: `visible`
    // starts false and is only flipped after mount, exactly like CookieBanner.
    const [completeProfileBannerVisible, setCompleteProfileBannerVisible] = useState(false);
    const { t } = useTranslation();
    const currentLocale = locale ?? 'fr';

    // Avatar fallback: first letter of the user's name, else their email's
    // first letter, else the generic User icon (avatar circle only exists for
    // authenticated users, so name/email are always present in practice).
    const userInitial =
        auth.user?.name?.trim().charAt(0).toUpperCase() ||
        auth.user?.email?.trim().charAt(0).toUpperCase() ||
        null;
    // "Pre-verification" only makes sense before the user is verified. The
    // shared prop degrades to null when the plugin's table is missing (the same
    // guard HandleInertiaRequests uses), so hide the item both for approved
    // users and when the plugin is absent — referencing a route that wouldn't
    // be registered would otherwise throw.
    const showPreVerificationItem =
        driverVerificationStatus !== null && driverVerificationStatus !== 'approved';

    // Local const so the profile dropdown's nested render-prop closure keeps
    // the null-narrowing TypeScript applies inside the `user ? ... : null`
    // ternary (property accesses like `auth.user` don't stay narrowed inside
    // nested arrow functions).
    const user = auth.user;

    const userId = user?.id ?? null;
    const bannerStorageKey = userId ? `driver-verification-banner-dismissed-${userId}` : null;
    const showCompleteProfileBanner =
        auth.user !== null && driverVerificationStatus === 'none';

    useEffect(() => {
        if (!showCompleteProfileBanner || !bannerStorageKey) {
            return;
        }
        setCompleteProfileBannerVisible(window.localStorage.getItem(bannerStorageKey) !== '1');
    }, [showCompleteProfileBanner, bannerStorageKey]);

    const dismissCompleteProfileBanner = () => {
        if (bannerStorageKey) {
            window.localStorage.setItem(bannerStorageKey, '1');
        }
        setCompleteProfileBannerVisible(false);
    };

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

    // Authenticated profile dropdown — circular avatar opening a Headless UI
    // Menu with My Reservations, optional Pre-verification, and Sign Out.
    // Language + Currency stay visible in the header bar for everyone (guest
    // and logged-in), matching the DiscoverCars/Hertz pattern.
    const profileMenu = user ? (
        <Menu as="div" className="relative">
            {({ close }) => (
                <>
                    <Menu.Button
                        type="button"
                        aria-label={t('Account menu')}
                        className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors hover:bg-primary/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                    >
                        {userInitial ? (
                            <span className="text-sm font-semibold leading-none">{userInitial}</span>
                        ) : (
                            <User className="h-4 w-4" aria-hidden="true" />
                        )}
                    </Menu.Button>

                    <Transition
                        enter="transition ease-out duration-100"
                        enterFrom="transform opacity-0 scale-95"
                        enterTo="transform opacity-100 scale-100"
                        leave="transition ease-in duration-75"
                        leaveFrom="transform opacity-100 scale-100"
                        leaveTo="transform opacity-0 scale-95"
                    >
                        <Menu.Items className="absolute right-0 z-50 mt-2 w-60 origin-top-right rounded-interactive bg-surface shadow-raised ring-1 ring-black ring-opacity-5 focus:outline-none">
                    <div className="border-b border-border px-4 py-3">
                        <p className="truncate text-sm font-semibold text-text">{user.name}</p>
                        <p className="truncate text-xs text-textMuted">{user.email}</p>
                    </div>

                    <div className="py-1">
                        <Menu.Item>
                            {({ active }) => (
                                <Link
                                    href={route('profile.edit')}
                                    className={`flex w-full items-center gap-2.5 px-4 py-2 text-sm text-text transition-colors focus:outline-none ${
                                        active ? 'bg-background' : ''
                                    }`}
                                >
                                    <CarFront className="h-4 w-4 shrink-0 text-textMuted" aria-hidden="true" />
                                    {t('My Reservations')}
                                </Link>
                            )}
                        </Menu.Item>

                        {showPreVerificationItem && (
                            <Menu.Item>
                                {({ active }) => (
                                    <Link
                                        href={route('driver-verification.show')}
                                        className={`flex w-full items-center gap-2.5 px-4 py-2 text-sm text-text transition-colors focus:outline-none ${
                                            active ? 'bg-background' : ''
                                        }`}
                                    >
                                        <IdCard className="h-4 w-4 shrink-0 text-textMuted" aria-hidden="true" />
                                        {t('Pre-verification')}
                                    </Link>
                                )}
                            </Menu.Item>
                        )}
                    </div>

                    <div className="border-t border-border py-1">
                        <Menu.Item>
                            {({ active }) => (
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className={`flex w-full items-center gap-2.5 px-4 py-2 text-sm text-text transition-colors focus:outline-none ${
                                        active ? 'bg-background' : ''
                                    }`}
                                >
                                    <LogOut className="h-4 w-4 shrink-0 text-textMuted" aria-hidden="true" />
                                    {t('Log Out')}
                                </Link>
                            )}
                        </Menu.Item>
                    </div>
                    </Menu.Items>
                    </Transition>
                </>
            )}
        </Menu>
    ) : null;

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

                    <nav className="hidden items-center gap-6 md:flex">
                        <Link href={route('vehicles.index')} className="text-sm font-semibold text-textMuted transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                            {t('Our Fleet')}
                        </Link>

                        <Link href={route('bookings.track')} className="text-sm font-semibold text-textMuted transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                            {t('Manage my booking')}
                        </Link>

                        {auth.user ? (
                            <>
                                <CurrencySelector />
                                {localeSwitcher}
                                {profileMenu}
                            </>
                        ) : (
                            <>
                                <CurrencySelector />
                                {localeSwitcher}
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
                        <Link href={route('bookings.track')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                            {t('Manage my booking')}
                        </Link>
                        {auth.user ? (
                            <>
                                <Link href={route('profile.edit')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                    {t('My Reservations')}
                                </Link>
                                {showPreVerificationItem && (
                                    <Link href={route('driver-verification.show')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing">
                                        {t('Pre-verification')}
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
                        <div className="flex items-center gap-1 pt-2">
                            <CurrencySelector align="left" />
                            {localeSwitcher}
                        </div>
                    </nav>
                )}
            </header>

            {showCompleteProfileBanner && completeProfileBannerVisible && (
                <div
                    role="status"
                    className="border-b border-border bg-surface px-4 py-3 sm:px-6 lg:px-8"
                >
                    <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
                        <p className="text-sm text-textMuted">
                            {t('Skip the line at pickup')} —{' '}
                            <Link
                                href={route('profile.edit')}
                                className="font-semibold text-primary underline underline-offset-2 hover:text-primaryHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                            >
                                {t('pre-verify your driver’s license (optional)')}
                            </Link>
                        </p>
                        <button
                            type="button"
                            onClick={dismissCompleteProfileBanner}
                            aria-label={t('Dismiss')}
                            className="flex-shrink-0 rounded-interactive p-1 text-textMuted transition-colors hover:bg-background hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}

            {/* One-time "enable notifications" nudge for logged-in users whose
                browser hasn't been asked yet. Renders nothing for guests, for
                browsers without Push support, once permission is granted, or
                after the user dismisses it. */}
            <NotificationBanner />

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
                    <Link href={route('info.driving-in-morocco')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-onPrimary">
                        {t('Driving in Morocco')}
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

            {/* Floating WhatsApp button — fixed bottom-right, the way Moroccan
                customers actually reach out (Medloc's #1 pattern). Uses
                WhatsApp brand green; sits above the cookie banner (bottom-20). */}
            <a
                href="https://wa.me/212600000000"
                target="_blank"
                rel="noopener noreferrer"
                aria-label={t('Chat on WhatsApp')}
                title={t('Chat on WhatsApp')}
                className="fixed bottom-20 right-4 z-50 flex h-12 w-12 items-center justify-center rounded-full bg-green-500 text-white shadow-raised transition-colors hover:bg-green-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focusRing"
            >
                <MessageCircle className="h-6 w-6" aria-hidden="true" />
            </a>

            {/* Cookie consent banner — fixed bottom, shown on every
                storefront page until the visitor accepts (localStorage). */}
            <CookieBanner />
        </div>
    );
}
