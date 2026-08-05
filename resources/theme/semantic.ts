/**
 * Tier 2 — Semantic tokens.
 *
 * This is THE file that changes per client. Do not import primitives directly
 * in components — import from here (or use the generated CSS variables).
 *
 * This file is the DEFAULT semantic layer. Client overrides live in
 * resources/theme/clients/ and are selected via resources/theme/active.ts,
 * which reads config/site.php's `active_theme` value.
 */
import { primitives as p } from './primitives';

export const semantic = {
    color: {
        primary:      p.color.blue[600],
        primaryHover: p.color.blue[500],
        onPrimary:    p.color.white,

        secondary:    p.color.amber[500],
        onSecondary:  p.color.gray[900],

        background:   p.color.gray[50],
        surface:      p.color.white,
        surfaceRaised: p.color.white,

        text:         p.color.gray[900],
        textMuted:    p.color.gray[600],
        border:       p.color.gray[300],

        success:      p.color.green[600],
        danger:       p.color.red[600],
        warning:      p.color.amber[600],

        focusRing:    p.color.blue[500],

        // Photo overlay tokens — fixed across all themes since they sit over arbitrary
        // uploaded photos, not a brand palette surface. onPhoto is the text/icon color;
        // photoScrim is the gradient color used at opacity (e.g. from-photoScrim/60).
        onPhoto:      p.color.white,
        photoScrim:   '#000000',
    },
    font: {
        display: p.font.family.spaceGrotesk,
        body:    p.font.family.inter,
        mono:    p.font.family.mono,
    },
    radius: {
        interactive: p.radius.md,
        container:   p.radius.lg,
        pill:        p.radius.full,
    },
    shadow: {
        resting: p.shadow.sm,
        raised:  p.shadow.md,
        overlay: p.shadow.lg,
    },
} as const;

/** Shape every client theme must satisfy. Use this as the return type in clients/*.ts */
export interface Semantic {
    color: {
        primary:      string;
        primaryHover: string;
        onPrimary:    string;
        secondary:    string;
        onSecondary:  string;
        background:   string;
        surface:      string;
        surfaceRaised: string;
        text:         string;
        textMuted:    string;
        border:       string;
        success:      string;
        danger:       string;
        warning:      string;
        focusRing:    string;
        onPhoto:      string;
        photoScrim:   string;
    };
    font: {
        display: string;
        body:    string;
        mono:    string;
    };
    radius: {
        interactive: string;
        container:   string;
        pill:        string;
    };
    shadow: {
        resting: string;
        raised:  string;
        overlay: string;
    };
}
