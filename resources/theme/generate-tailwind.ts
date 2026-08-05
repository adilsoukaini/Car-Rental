/**
 * Exports a Tailwind theme extension backed by CSS variables.
 *
 * Because the values are var(--...) references (not hard-coded hex),
 * Tailwind utility classes like bg-primary, font-display, rounded-interactive
 * automatically pick up the active client theme at runtime without a rebuild.
 *
 * Usage in tailwind.config.js:
 *   const { tailwindTheme } = require('./resources/theme/generate-tailwind');
 *   module.exports = { theme: { extend: tailwindTheme } };
 */
export const tailwindTheme = {
    colors: {
        primary:      'var(--color-primary)',
        primaryHover: 'var(--color-primaryHover)',
        onPrimary:    'var(--color-onPrimary)',
        secondary:    'var(--color-secondary)',
        onSecondary:  'var(--color-onSecondary)',
        background:   'var(--color-background)',
        surface:      'var(--color-surface)',
        text:         'var(--color-text)',
        textMuted:    'var(--color-textMuted)',
        border:       'var(--color-border)',
        danger:       'var(--color-danger)',
        success:      'var(--color-success)',
        warning:      'var(--color-warning)',
        focusRing:    'var(--color-focusRing)',
        onPhoto:      'var(--color-onPhoto)',
        photoScrim:   'var(--color-photoScrim)',
    },
    fontFamily: {
        display: ['var(--font-display)'],
        body:    ['var(--font-body)'],
        mono:    ['var(--font-mono)'],
    },
    borderRadius: {
        interactive: 'var(--radius-interactive)',
        container:   'var(--radius-container)',
        pill:        'var(--radius-pill)',
    },
    boxShadow: {
        resting: 'var(--shadow-resting)',
        raised:  'var(--shadow-raised)',
        overlay: 'var(--shadow-overlay)',
    },
} as const;
