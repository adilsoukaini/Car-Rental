/**
 * Typography scale — named styles mapping to {family, size, weight, lineHeight}.
 *
 * Use the <Text variant="..."> component rather than picking raw Tailwind text
 * classes per-component. This prevents heading-size drift across developers.
 */
export const typography = {
    'display.hero': { family: 'display', size: '3rem',    weight: 700, lineHeight: 1.1 },
    'display.h1':   { family: 'display', size: '2.25rem', weight: 700, lineHeight: 1.2 },
    'display.h2':   { family: 'display', size: '1.875rem',weight: 600, lineHeight: 1.2 },
    'display.h3':   { family: 'display', size: '1.5rem',  weight: 600, lineHeight: 1.3 },
    'body.lg':      { family: 'body',    size: '1.125rem',weight: 400, lineHeight: 1.75 },
    'body.base':    { family: 'body',    size: '1rem',    weight: 400, lineHeight: 1.5 },
    'body.sm':      { family: 'body',    size: '0.875rem',weight: 400, lineHeight: 1.5 },
    'mono.price':   { family: 'mono',    size: '1.125rem',weight: 600, lineHeight: 1.4 },
    'mono.sku':     { family: 'mono',    size: '0.75rem', weight: 400, lineHeight: 1.4 },
} as const;

export type TypographyVariant = keyof typeof typography;
