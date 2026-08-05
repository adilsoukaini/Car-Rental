import { semantic } from './active';
import { components } from './components';
import type { Semantic } from './semantic';

function flatten(obj: Record<string, any>, prefix = ''): Record<string, string> {
    return Object.entries(obj).reduce((acc, [key, value]) => {
        const name = prefix ? `${prefix}-${key}` : key;
        if (value !== null && typeof value === 'object') {
            Object.assign(acc, flatten(value as Record<string, any>, name));
        } else {
            acc[`--${name}`] = String(value);
        }
        return acc;
    }, {} as Record<string, string>);
}

/**
 * Returns a flat Record<string, string> of CSS custom property name → value.
 *
 * Semantic tokens  → --color-primary, --font-display, --radius-interactive, etc.
 * Component tokens → --c-button-primary-bg, --c-card-radius, etc.
 *
 * ThemeProvider applies these to document.documentElement so every component
 * can use var(--color-primary) or the Tailwind semantic class bg-primary.
 */
export function cssVariables(): Record<string, string> {
    return {
        ...flatten(semantic.color,  'color'),
        ...flatten(semantic.font,   'font'),
        ...flatten(semantic.radius, 'radius'),
        ...flatten(semantic.shadow, 'shadow'),
        ...flatten(components,      'c'),
    };
}

/**
 * Same flatten logic as cssVariables(), but takes a Semantic data object as
 * input rather than reading from the statically-imported active.ts module.
 *
 * Used by ThemeProvider to apply DB-driven runtime theme data — the result
 * is layered on top of cssVariables() so a DB theme fully overrides the
 * bundled-at-build-time semantic vars without touching component tokens.
 */
export function cssVariablesFrom(data: Semantic): Record<string, string> {
    return {
        ...flatten(data.color,  'color'),
        ...flatten(data.font,   'font'),
        ...flatten(data.radius, 'radius'),
        ...flatten(data.shadow, 'shadow'),
    };
}
