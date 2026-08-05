/**
 * Default client theme — blue + amber, rounded corners, Poppins/Inter.
 * Copy this file to clients/client-<name>.ts and edit only `semantic`
 * to create a new client theme.
 */
import { primitives as p } from '../primitives';
import type { Semantic } from '../semantic';

export const semantic: Semantic = {
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

        onPhoto:      p.color.white,
        photoScrim:   '#000000',
    },
    font: {
        display: p.font.family.poppins,
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
};
