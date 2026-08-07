import { createElement } from 'react';

/**
 * Resolves the typography scale from resources/theme/typography.ts into a
 * reusable <Text> component.
 *
 * Each variant maps to a Tailwind class combination derived from the scale's
 * {family, size, weight, lineHeight} tokens (the tokens themselves are
 * documented there; this is the component-side mapping so heading/body sizes
 * don't drift across components). Color defaults to the `text` theme token —
 * override with className when a different token is needed.
 */

type TextVariant =
    | 'hero'
    | 'h1'
    | 'h2'
    | 'h3'
    | 'body-lg'
    | 'body-base'
    | 'body-sm'
    | 'mono-price'
    | 'mono-sku';

interface TextVariantConfig {
    as: keyof JSX.IntrinsicElements;
    className: string;
}

const variantConfig: Record<TextVariant, TextVariantConfig> = {
    hero: { as: 'h1', className: 'font-display text-5xl font-bold leading-tight tracking-tight text-text' },
    h1: { as: 'h1', className: 'font-display text-4xl font-bold leading-tight tracking-tight text-text' },
    h2: { as: 'h2', className: 'font-display text-3xl font-semibold leading-tight tracking-tight text-text' },
    h3: { as: 'h3', className: 'font-display text-2xl font-semibold leading-tight tracking-tight text-text' },
    'body-lg': { as: 'p', className: 'font-body text-lg font-normal leading-relaxed tracking-normal text-text' },
    'body-base': { as: 'p', className: 'font-body text-base font-normal leading-normal tracking-normal text-text' },
    'body-sm': { as: 'p', className: 'font-body text-sm font-normal leading-normal tracking-normal text-text' },
    'mono-price': { as: 'p', className: 'font-mono text-lg font-semibold leading-normal tracking-normal text-text' },
    'mono-sku': { as: 'p', className: 'font-mono text-xs font-normal leading-normal tracking-wide text-text' },
};

interface TextProps extends React.HTMLAttributes<HTMLElement> {
    variant: TextVariant;
    as?: keyof JSX.IntrinsicElements;
    className?: string;
}

export default function Text({
    variant,
    as,
    className = '',
    ...rest
}: TextProps) {
    const config = variantConfig[variant];
    const Tag = as ?? config.as;

    return createElement(Tag, {
        className: `${config.className} ${className}`.trim(),
        ...rest,
    });
}
