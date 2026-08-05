/**
 * Tier 3 — Component tokens.
 *
 * Maps semantic tokens to component-specific parts. Components read these
 * (via CSS variables) — they never reference semantic or primitive values
 * directly.
 *
 * This file is shared across all clients. Only semantic.ts changes per client;
 * components.ts stays constant, which is what makes re-theming one-file.
 */
import { semantic as s } from './active';

export const components = {
    button: {
        primary:   { bg: s.color.primary, bgHover: s.color.primaryHover, text: s.color.onPrimary, radius: s.radius.interactive },
        secondary: { bg: 'transparent', border: s.color.primary, text: s.color.primary, radius: s.radius.interactive },
        danger:    { bg: s.color.danger, bgHover: s.color.danger, text: s.color.onPrimary, radius: s.radius.interactive },
    },
    card: {
        bg:     s.color.surface,
        border: s.color.border,
        radius: s.radius.container,
        shadow: s.shadow.resting,
    },
    input: {
        bg:          s.color.surface,
        border:      s.color.border,
        focusBorder: s.color.focusRing,
        radius:      s.radius.interactive,
        text:        s.color.text,
        placeholder: s.color.textMuted,
    },
    badge: {
        bg:     s.color.secondary,
        text:   s.color.onSecondary,
        radius: s.radius.pill,
    },
    vehicleCard: {
        bg:         s.color.surface,
        titleFont:  s.font.display,
        priceFont:  s.font.mono,
        radius:     s.radius.container,
        shadow:     s.shadow.resting,
    },
} as const;

export type Components = typeof components;
