/**
 * DISPOSABLE — NOT A REAL CLIENT. This file exists solely as a second data
 * point to prove theme swapping actually works (Phase 3 verification):
 * swap semantic.ts values only, zero component edits, no rebuild required.
 * Delete this file (and its entry in active.ts) once a real second client
 * theme is needed — do not mistake "Demo Rentals" for an onboarded client.
 *
 * Contrast check (WCAG 4.5:1 required on onX pairs):
 *   onPrimary (#fff) on primary (#0f5132)   → ~8.9:1  ✓
 *   onSecondary (#1a1a1a) on secondary (#f0a500) → ~7.9:1  ✓
 */
import { primitives as p } from '../primitives';
import type { Semantic } from '../semantic';

export const semantic: Semantic = {
    color: {
        primary:      '#0f5132',
        primaryHover: '#146c43',
        onPrimary:    '#ffffff',

        secondary:    '#f0a500',
        onSecondary:  '#1a1a1a',

        background:   '#f5f7f5',
        surface:      '#ffffff',
        surfaceRaised: '#ffffff',

        text:         '#1a1a1a',
        textMuted:    p.color.gray[600],
        border:       p.color.gray[300],

        success:      p.color.green[600],
        danger:       p.color.red[600],
        warning:      p.color.amber[600],

        focusRing:    '#0f5132',

        onPhoto:      p.color.white,
        photoScrim:   '#000000',
    },
    font: {
        display: p.font.family.poppins,
        body:    p.font.family.inter,
        mono:    p.font.family.mono,
    },
    radius: {
        interactive: p.radius.lg,
        container:   p.radius.lg,
        pill:        p.radius.full,
    },
    shadow: {
        resting: p.shadow.sm,
        raised:  p.shadow.md,
        overlay: p.shadow.lg,
    },
};
