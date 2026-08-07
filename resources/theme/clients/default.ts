/**
 * Default client theme — matches the Stitch "Premium Mobility Design System"
 * exactly. Deep Atlantic Navy primary, Electric Blue accent, clean white
 * surfaces with blue tint, Inter typography.
 *
 * Copy this file to clients/client-<name>.ts and edit only `semantic`
 * to create a new client theme.
 */
import { primitives as p } from '../primitives';
import type { Semantic } from '../semantic';

export const semantic: Semantic = {
    color: {
        // Deep Atlantic Navy — Stitch primary
        primary:      '#0A1F44',
        primaryHover: '#0D2857',
        onPrimary:    p.color.white,

        // Electric Blue — Stitch accent
        secondary:    '#0047FF',
        onSecondary:  p.color.white,

        // Clean white with blue tint
        background:   '#F8F9FF',
        surface:      p.color.white,
        surfaceRaised: p.color.white,

        // High-contrast dark text
        text:         '#0B1C30',
        textMuted:    '#64748B',
        border:       '#C5C6CF',

        // Semantic
        // success/warning darkened from green-600/amber-600 to green-700/amber-700
        // so text usage meets WCAG AA (>=4.5:1) on both background and surface.
        success:      '#15803D',
        danger:       '#BA1A1A',
        warning:      '#B45309',

        focusRing:    '#0047FF',

        // Photo overlay tokens
        onPhoto:      p.color.white,
        photoScrim:   '#000000',
    },
    font: {
        display: p.font.family.spaceGrotesk,
        body:    p.font.family.inter,
        mono:    p.font.family.mono,
    },
    radius: {
        interactive: '8px',
        container:   '12px',
        pill:        p.radius.full,
    },
    shadow: {
        resting: '0 1px 3px rgba(0,0,0,0.08)',
        raised:  '0 4px 12px rgba(0,0,0,0.12)',
        overlay: '0 8px 24px rgba(0,0,0,0.16)',
    },
};
