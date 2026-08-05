/**
 * Merged token export — the single object ThemeProvider consumes.
 *
 * Import order matters: semantic comes from active.ts (the selected client
 * theme), so components.ts sees the right values when it imports semantic.
 */
import { primitives } from './primitives';
import { semantic }   from './active';
import { components } from './components';
import { typography } from './typography';

export const tokens = { primitives, semantic, components, typography };
export type Tokens = typeof tokens;
