export const primitives = {
    color: {
        blue:  { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 900: '#1e3a8a' },
        gray:  { 50: '#fafafa', 100: '#f4f4f5', 300: '#d4d4d8', 600: '#52525b', 900: '#18181b' },
        amber: { 50: '#fffbeb', 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' },
        red:   { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626' },
        green: { 50: '#f0fdf4', 500: '#22c55e', 600: '#16a34a' },
        white: '#ffffff',
        black: '#000000',
    },
    font: {
        family: {
            poppins:      '"Poppins", sans-serif',
            spaceGrotesk: '"Space Grotesk", sans-serif',
            inter:        '"Inter", sans-serif',
            playfair:     '"Playfair Display", serif',
            mono:         '"JetBrains Mono", monospace',
        },
        size: {
            xs:   '0.75rem',
            sm:   '0.875rem',
            base: '1rem',
            lg:   '1.125rem',
            xl:   '1.25rem',
            '2xl': '1.5rem',
            '3xl': '1.875rem',
            '4xl': '2.25rem',
            '5xl': '3rem',
        },
        weight: { regular: 400, medium: 500, semibold: 600, bold: 700 },
        lineHeight: { tight: 1.2, normal: 1.5, relaxed: 1.75 },
    },
    space: {
        0: '0',
        1: '4px',
        2: '8px',
        3: '12px',
        4: '16px',
        6: '24px',
        8: '32px',
        12: '48px',
        16: '64px',
    },
    radius: { none: '0', sm: '4px', md: '8px', lg: '16px', full: '9999px' },
    shadow: {
        sm: '0 1px 2px rgba(0,0,0,0.05)',
        md: '0 4px 6px rgba(0,0,0,0.1)',
        lg: '0 10px 15px rgba(0,0,0,0.1)',
    },
} as const;

export type Primitives = typeof primitives;
