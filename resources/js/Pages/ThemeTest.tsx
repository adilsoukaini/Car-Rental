import { Head } from '@inertiajs/react';

/**
 * Throwaway Phase 3 verification page — proves primitives -> semantic ->
 * component tokens apply via CSS variables, using only token-driven Tailwind
 * classes (no hardcoded colors/fonts/spacing/radius). Remove once a real
 * themed page exists to demonstrate this instead.
 */
export default function ThemeTest({ activeTheme }: { activeTheme: string }) {
    return (
        <div className="min-h-screen bg-background p-8 font-body text-text">
            <Head title="Theme Test" />

            <p className="mb-6 text-sm text-textMuted">
                Active theme: <span className="font-mono">{activeTheme}</span>
            </p>

            <h1 className="mb-8 font-display text-3xl font-bold text-text">
                Theme Token Test Page
            </h1>

            <div className="mb-8 flex flex-wrap gap-4">
                <button className="rounded-interactive bg-primary px-4 py-2 font-body text-onPrimary shadow-resting hover:bg-primaryHover">
                    Primary Button
                </button>
                <button className="rounded-interactive border border-primary px-4 py-2 font-body text-primary">
                    Secondary Button
                </button>
                <button className="rounded-interactive bg-danger px-4 py-2 font-body text-onPrimary">
                    Danger Button
                </button>
                <span className="rounded-pill bg-secondary px-3 py-1 font-body text-sm text-onSecondary">
                    Badge
                </span>
            </div>

            <div className="mb-8 max-w-sm rounded-container border border-border bg-surface p-6 shadow-resting">
                <h2 className="mb-2 font-display text-xl font-semibold text-text">
                    Vehicle Card (example)
                </h2>
                <p className="mb-4 font-body text-sm text-textMuted">
                    Dacia Duster — SUV, 5 seats, manual
                </p>
                <p className="font-mono text-lg font-semibold text-text">
                    450.00 MAD / day
                </p>
            </div>

            <div className="max-w-sm rounded-interactive border border-border bg-surface p-4">
                <label className="mb-1 block text-sm text-textMuted">
                    Sample input (input tokens)
                </label>
                <input
                    type="text"
                    placeholder="Pickup location"
                    className="w-full rounded-interactive border border-border bg-surface px-3 py-2 text-text placeholder-textMuted focus:border-focusRing focus:outline-none"
                />
            </div>
        </div>
    );
}
