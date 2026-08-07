import SiteLogo from '@/Components/SiteLogo';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
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
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth, driverVerificationStatus, siteIdentity } = usePage<
        PageProps & { siteIdentity?: { siteName?: string } }
    >().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const needsDriverVerification =
        auth.user !== null && driverVerificationStatus !== null && driverVerificationStatus !== 'approved';

    return (
        <div className="flex min-h-screen flex-col bg-background font-body text-text">
            <header className="sticky top-0 z-50 border-b border-border bg-surface/90 backdrop-blur-md">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <Link href="/">
                        <SiteLogo />
                    </Link>

                    <nav className="hidden items-center gap-8 md:flex">
                        <Link href={route('vehicles.index')} className="text-sm font-semibold text-textMuted transition-colors hover:text-primary">
                            Our Fleet
                        </Link>

                        {auth.user ? (
                            <>
                                <Link href={route('profile.edit')} className="text-sm font-semibold text-textMuted transition-colors hover:text-primary">
                                    My Account
                                </Link>
                                {needsDriverVerification && (
                                    <Link
                                        href={route('driver-verification.show')}
                                        className="text-sm font-semibold text-warning transition-colors hover:text-primary"
                                    >
                                        Driver Verification
                                    </Link>
                                )}
                                <Link href={route('logout')} method="post" as="button" className="text-sm font-semibold text-textMuted transition-colors hover:text-primary">
                                    Log Out
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className="text-sm font-semibold text-textMuted transition-colors hover:text-primary">
                                    Log In
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-interactive bg-primary px-4 py-2 text-sm font-semibold text-onPrimary transition-colors hover:bg-primaryHover"
                                >
                                    Sign Up
                                </Link>
                            </>
                        )}
                    </nav>

                    <button
                        type="button"
                        className="text-text md:hidden"
                        onClick={() => setMobileMenuOpen((open) => !open)}
                        aria-label="Toggle menu"
                    >
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                {mobileMenuOpen && (
                    <nav className="flex flex-col gap-1 border-t border-border bg-surface px-4 py-3 md:hidden">
                        <Link href={route('vehicles.index')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background">
                            Our Fleet
                        </Link>
                        {auth.user ? (
                            <>
                                <Link href={route('profile.edit')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background">
                                    My Account
                                </Link>
                                {needsDriverVerification && (
                                    <Link href={route('driver-verification.show')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-warning hover:bg-background">
                                        Driver Verification
                                    </Link>
                                )}
                                <Link href={route('logout')} method="post" as="button" className="rounded-interactive px-2 py-2 text-left text-sm font-semibold text-textMuted hover:bg-background">
                                    Log Out
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background">
                                    Log In
                                </Link>
                                <Link href={route('register')} className="rounded-interactive px-2 py-2 text-sm font-semibold text-textMuted hover:bg-background">
                                    Sign Up
                                </Link>
                            </>
                        )}
                    </nav>
                )}
            </header>

            <main className="flex-grow">{children}</main>

            <footer className="mt-auto grid grid-cols-1 gap-8 bg-primary px-4 py-16 text-onPrimary sm:px-6 md:grid-cols-4 lg:px-8">
                <div className="space-y-4">
                    <SiteLogo textClassName="text-onPrimary" iconClassName="text-onPrimary" />
                    <p className="text-sm text-onPrimary/80">Your trusted partner for premium, hassle-free mobility.</p>
                </div>

                <div className="flex flex-col gap-3">
                    <span className="text-sm font-semibold">Browse</span>
                    <Link href={route('vehicles.index')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary">
                        Our Fleet
                    </Link>
                </div>

                <div className="flex flex-col gap-3">
                    <span className="text-sm font-semibold">Account</span>
                    <Link href={route('bookings.track')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary">
                        Track your booking
                    </Link>
                    {auth.user ? (
                        <Link href={route('profile.edit')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary">
                            My Account
                        </Link>
                    ) : (
                        <>
                            <Link href={route('login')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary">
                                Log In
                            </Link>
                            <Link href={route('register')} className="text-sm text-onPrimary/80 transition-colors hover:text-onPrimary">
                                Sign Up
                            </Link>
                        </>
                    )}
                </div>

                <div className="flex flex-col justify-end text-sm text-onPrimary/80 md:text-right">
                    © {new Date().getFullYear()} {siteIdentity?.siteName ?? 'Car Rental'}. All rights reserved.
                </div>
            </footer>
        </div>
    );
}
