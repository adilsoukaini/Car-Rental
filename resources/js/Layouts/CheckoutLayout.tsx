import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

/**
 * Minimal, distraction-free layout for the booking/payment flow.
 *
 * The checkout and payment pages render Stripe Elements and real money
 * decisions, so they deliberately drop PublicLayout's full header/nav/footer
 * in favour of a bare centered shell: brand, a subtle "Secure checkout"
 * badge, the page content, and a one-line footer. Every color/spacing value
 * goes through this project's own theme tokens (Hard Rule 3).
 */
export default function CheckoutLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col bg-background font-body text-text">
            <header className="border-b border-border bg-surface">
                <div className="mx-auto flex w-full max-w-xl items-center justify-between px-4 py-4 sm:px-6">
                    <Link href="/" className="font-display text-xl font-black tracking-tight text-primary">
                        Car Rental
                    </Link>

                    <span className="inline-flex items-center gap-1.5 rounded-pill border border-border bg-background px-3 py-1 text-xs font-semibold text-textMuted">
                        <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                            />
                        </svg>
                        Secure checkout
                    </span>
                </div>
            </header>

            <main className="flex flex-1 items-start justify-center px-4 py-8 sm:px-6">
                <div className="w-full max-w-xl">{children}</div>
            </main>

            <footer className="mt-auto px-4 py-6 text-center">
                <p className="text-xs text-textMuted">
                    &copy; {new Date().getFullYear()} Car Rental. All rights reserved.
                </p>
                <p className="mt-1 text-xs text-textMuted">Powered by Car Rental</p>
            </footer>
        </div>
    );
}
