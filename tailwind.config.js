import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,tsx}',
        './plugins/**/resources/js/**/*.{ts,tsx}',
    ],

    theme: {
        extend: {
            colors: {
                primary: 'var(--color-primary)',
                primaryHover: 'var(--color-primaryHover)',
                onPrimary: 'var(--color-onPrimary)',
                secondary: 'var(--color-secondary)',
                onSecondary: 'var(--color-onSecondary)',
                background: 'var(--color-background)',
                surface: 'var(--color-surface)',
                text: 'var(--color-text)',
                textMuted: 'var(--color-textMuted)',
                border: 'var(--color-border)',
                danger: 'var(--color-danger)',
                success: 'var(--color-success)',
                warning: 'var(--color-warning)',
                focusRing: 'var(--color-focusRing)',
                onPhoto: 'var(--color-onPhoto)',
                photoScrim: 'var(--color-photoScrim)',
            },
            fontFamily: {
                display: ['var(--font-display)', ...defaultTheme.fontFamily.serif],
                body: ['var(--font-body)', ...defaultTheme.fontFamily.sans],
                mono: ['var(--font-mono)', ...defaultTheme.fontFamily.mono],
                sans: ['var(--font-body)', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                interactive: 'var(--radius-interactive)',
                container: 'var(--radius-container)',
                pill: 'var(--radius-pill)',
            },
            boxShadow: {
                resting: 'var(--shadow-resting)',
                raised: 'var(--shadow-raised)',
                overlay: 'var(--shadow-overlay)',
            },
        },
    },

    plugins: [forms],
};
