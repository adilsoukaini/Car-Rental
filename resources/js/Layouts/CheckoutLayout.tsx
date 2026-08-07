import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import { PropsWithChildren } from 'react';

/**
 * Minimal, distraction-free shell for the booking/payment flow.
 *
 * The checkout and payment pages render Stripe Elements and real money
 * decisions, so they deliberately drop PublicLayout's full header/nav/footer
 * in favour of a bare centered shell. Matches the Stitch "Project Atlas"
 * checkout design: a back button + brand on the left, a 3-step progress
 * stepper (desktop only) in the middle, and a "Paiement sécurisé" badge on
 * the right. Every color/spacing value goes through this project's own theme
 * tokens (Hard Rule 3); the brand name comes from the shared site identity.
 */
interface CheckoutLayoutProps {
    /** Where the circular back button links. Defaults to the storefront root. */
    backHref?: string;
    backLabel?: string;
}

export default function CheckoutLayout({
    backHref = '/',
    backLabel = 'Retour',
    children,
}: PropsWithChildren<CheckoutLayoutProps>) {
    const { siteIdentity } = usePage<{ siteIdentity?: { siteName?: string } }>().props;
    const siteName = siteIdentity?.siteName ?? 'Car Rental';

    return (
        <div className="flex min-h-screen flex-col bg-background font-body text-text">
            <header className="border-b border-border bg-surface">
                <div className="mx-auto grid w-full max-w-6xl grid-cols-[auto_1fr_auto] items-center px-4 py-4 sm:px-6">
                    {/* Back button + brand */}
                    <div className="flex items-center gap-3">
                        <Link
                            href={backHref}
                            aria-label={backLabel}
                            className="flex h-10 w-10 items-center justify-center rounded-full text-text transition-colors hover:bg-background"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <Link href="/" className="font-display text-xl font-black tracking-tight text-primary">
                            {siteName}
                        </Link>
                    </div>

                    {/* Stepper — desktop only, sits between logo and secure badge */}
                    <div className="hidden justify-center md:flex">
                        <Stepper />
                    </div>

                    <span className="inline-flex items-center justify-self-end gap-1.5 text-xs font-semibold text-textMuted">
                        <Lock className="h-3.5 w-3.5" />
                        Paiement sécurisé
                    </span>
                </div>
            </header>

            <main className="flex flex-1 justify-center">
                <div className="w-full max-w-6xl px-4 sm:px-6 lg:px-8">{children}</div>
            </main>
        </div>
    );
}

/**
 * The 3-step checkout progress indicator from the Stitch design:
 * ① Véhicule —— ② Options —— ③ Paiement. Hidden on mobile (the flow is
 * already shallow enough that the stepper adds noise on small screens).
 *
 * Steps 1–2 are "completed" (Electric Blue secondary), step 3 is "current"
 * (Navy primary). The connector is a thin 8×1px line in the outline/border
 * color. All colors come from theme tokens.
 */
function Stepper() {
    const steps = [
        { number: 1, label: 'Véhicule', state: 'done' as const },
        { number: 2, label: 'Options', state: 'done' as const },
        { number: 3, label: 'Paiement', state: 'current' as const },
    ];

    return (
        <ol className="flex items-center" aria-label="Étapes de réservation">
            {steps.map((step, index) => (
                <li key={step.number} className="flex items-center">
                    {index > 0 && <div className="mx-2 h-px w-8 bg-border" aria-hidden="true" />}
                    <div className="flex flex-col items-center gap-1">
                        <div
                            className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white ${
                                step.state === 'done' ? 'bg-secondary' : 'bg-primary'
                            }`}
                        >
                            {step.number}
                        </div>
                        <span
                            className={`text-xs ${
                                step.state === 'current' ? 'font-bold text-primary' : 'font-semibold text-textMuted'
                            }`}
                        >
                            {step.label}
                        </span>
                    </div>
                </li>
            ))}
        </ol>
    );
}
