/**
 * Re-exports the semantic layer for whichever client theme is active.
 *
 * The active theme is controlled by ACTIVE_THEME in .env (read by config/site.php).
 * Switching a whole deployment's identity: change one env var, no component edits.
 *
 * To add a new client:
 *   1. Copy clients/default.ts → clients/client-<name>.ts
 *   2. Edit semantic values only — never touch components.ts or component code
 *   3. Import it below and add it to the `themes` map
 *   4. Set ACTIVE_THEME=<name> in .env
 */
import * as defaultTheme from './clients/default';
// DISPOSABLE — proves swapping works, not a real client. See the file itself.
import * as swapProof from './clients/client-swap-proof-DISPOSABLE';

type ThemeId = 'default' | 'demo-rentals';

const themes: Record<ThemeId, { semantic: typeof defaultTheme.semantic }> = {
    'default': defaultTheme,
    'demo-rentals': swapProof,
};

// The active theme ID is injected by the Laravel backend via window.__THEME__
// (set in app.blade.php from config('site.active_theme')). Falls back to 'default'.
const activeId = (
    typeof window !== 'undefined' ? window.__THEME__ : undefined
) as ThemeId | undefined;

export const semantic = (activeId && themes[activeId] ? themes[activeId] : themes['default']).semantic;
