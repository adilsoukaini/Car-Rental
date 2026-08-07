/**
 * Shared styling constants + helpers for the checkout flow, extracted so the
 * checkout page (which switches between the sidebar and vertical-stack layout
 * variants) and its reusable form/summary components stay in sync.
 */

/** Stitch input styling: #F4F7FA is a Stitch-specific field fill kept as a direct hex (part of the visual identity, not a theme token). */
export const inputBase =
    'w-full bg-[#F4F7FA] border border-border px-4 py-3 text-sm text-text placeholder:text-textMuted focus:border-secondary focus:ring-1 focus:ring-secondary outline-none transition-all';
export const inputClass = `${inputBase} rounded-interactive`;
export const phoneInputClass = `${inputBase} rounded-r-interactive`;
export const phonePrefixClass =
    'inline-flex items-center rounded-l-interactive border border-r-0 border-border bg-[#F4F7FA] px-3 text-sm text-textMuted';

/** Low-opacity tints — opacity modifiers on CSS-var theme tokens don't generate classes in this Tailwind v3 setup, so tint with color-mix inline. */
export const tintSecondary = { backgroundColor: 'color-mix(in srgb, var(--color-secondary) 12%, transparent)' };
export const tintPrimary = { backgroundColor: 'color-mix(in srgb, var(--color-primary) 10%, transparent)' };
export const tintDanger = { backgroundColor: 'color-mix(in srgb, var(--color-danger) 6%, transparent)' };

export function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString('fr-FR', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
