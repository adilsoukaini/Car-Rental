import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import { tailwindTheme } from './resources/theme/generate-tailwind.ts';

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
            ...tailwindTheme,
            fontFamily: {
                ...tailwindTheme.fontFamily,
                display: ['var(--font-display)', ...defaultTheme.fontFamily.serif],
                body: ['var(--font-body)', ...defaultTheme.fontFamily.sans],
                mono: ['var(--font-mono)', ...defaultTheme.fontFamily.mono],
                sans: ['var(--font-body)', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
