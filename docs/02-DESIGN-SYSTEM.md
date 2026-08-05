# Design System Architecture — Single-File Theming Engine

**Goal:** one file (per client) controls every color, font, spacing value, radius, and
shadow across the whole app, with zero hunting through components to re-skin a site.

The pattern used here — **primitive tokens → semantic tokens → component tokens** — is
the same three-tier structure used by Style Dictionary, Radix, and most serious design
systems. It's what lets "change the brand color" be a one-line edit instead of a
find-and-replace across 40 files.

---

## 1. The three tiers

```
TIER 1 — Primitives          TIER 2 — Semantic              TIER 3 — Component
(raw values, no meaning)     (meaning, no raw values)        (component-specific)

blue-500: #3B82F6      →     color.primary: {blue-500}   →   button.bg: {color.primary}
gray-900: #111111      →     color.text: {gray-900}      →   button.text: {color.onPrimary}
gray-50:  #FAFAFA      →     color.background: {gray-50} →   card.bg: {color.surface}
```

**Why three tiers and not one flat file:** if you only had `button.bg: #3B82F6`, then
changing the whole brand color means editing 40 different `bg` properties across every
component's tokens. With semantic tokens in between, you change `color.primary` once,
and everything referencing it updates. Primitives exist so you have a named, constrained
palette instead of hex codes sprinkled everywhere ("is this blue the same as that blue?").

**Rule:** components only ever reference Tier 3 (or Tier 2 directly for simple cases).
They never hardcode a Tier 1 primitive or a raw hex value. This is the rule that makes
re-theming possible.

---

## 2. Token file structure

```
/theme
  /primitives.ts     ← Tier 1: raw palette, raw type scale, raw spacing scale
  /semantic.ts        ← Tier 2: maps primitives to meaning (per-client, this is what changes most)
  /components.ts      ← Tier 3: maps semantic tokens to component parts
  /tokens.ts           ← merges all three, exported as one theme object
  ThemeProvider.tsx    ← injects CSS variables at runtime
  generate-tailwind.ts ← turns tokens.ts into tailwind.config.js extension
```

### Tier 1 — primitives.ts

```typescript
export const primitives = {
  color: {
    blue: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 900: '#1e3a8a' },
    gray: { 50: '#fafafa', 100: '#f4f4f5', 300: '#d4d4d8', 600: '#52525b', 900: '#18181b' },
    amber:{ 50: '#fffbeb', 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' },
    red:  { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626' },
    green:{ 50: '#f0fdf4', 500: '#22c55e', 600: '#16a34a' },
  },
  font: {
    family: {
      poppins: '"Poppins", sans-serif',
      inter: '"Inter", sans-serif',
      playfair: '"Playfair Display", serif',
      mono: '"JetBrains Mono", monospace',
    },
    size: {
      xs: '0.75rem', sm: '0.875rem', base: '1rem', lg: '1.125rem',
      xl: '1.25rem', '2xl': '1.5rem', '3xl': '1.875rem', '4xl': '2.25rem', '5xl': '3rem',
    },
    weight: { regular: 400, medium: 500, semibold: 600, bold: 700 },
    lineHeight: { tight: 1.2, normal: 1.5, relaxed: 1.75 },
  },
  space: { 0: '0', 1: '4px', 2: '8px', 3: '12px', 4: '16px', 6: '24px', 8: '32px', 12: '48px', 16: '64px' },
  radius: { none: '0', sm: '4px', md: '8px', lg: '16px', full: '9999px' },
  shadow: {
    sm: '0 1px 2px rgba(0,0,0,0.05)',
    md: '0 4px 6px rgba(0,0,0,0.1)',
    lg: '0 10px 15px rgba(0,0,0,0.1)',
  },
} as const;
```

### Tier 2 — semantic.ts (**this is the file you swap per client**)

```typescript
import { primitives as p } from './primitives';

export const semantic = {
  color: {
    primary:        p.color.blue[600],
    primaryHover:    p.color.blue[500],
    onPrimary:       '#ffffff',

    secondary:       p.color.amber[500],
    onSecondary:     p.color.gray[900],

    background:      p.color.gray[50],
    surface:         '#ffffff',
    surfaceRaised:   '#ffffff',

    text:            p.color.gray[900],
    textMuted:       p.color.gray[600],
    border:          p.color.gray[300],

    success:         p.color.green[600],
    danger:          p.color.red[600],
    warning:         p.color.amber[600],
  },
  font: {
    display: p.font.family.poppins,   // headings
    body: p.font.family.inter,        // paragraphs, UI
    mono: p.font.family.mono,          // prices, SKUs, code
  },
  radius: {
    interactive: p.radius.md,   // buttons, inputs
    container: p.radius.lg,     // cards, modals
    pill: p.radius.full,        // badges, tags
  },
  shadow: {
    resting: p.shadow.sm,
    raised: p.shadow.md,
    overlay: p.shadow.lg,
  },
} as const;

export type Semantic = typeof semantic;
```

**This is the single file that changes per client.** A new client with a navy-and-serif
identity is: swap `primary` to their navy hex, swap `font.display` to their serif, done.
Nothing else in the codebase needs to know.

### Tier 3 — components.ts

```typescript
import { semantic as s } from './semantic';

export const components = {
  button: {
    primary: { bg: s.color.primary, bgHover: s.color.primaryHover, text: s.color.onPrimary, radius: s.radius.interactive },
    secondary: { bg: 'transparent', border: s.color.primary, text: s.color.primary, radius: s.radius.interactive },
    danger: { bg: s.color.danger, text: '#ffffff', radius: s.radius.interactive },
  },
  card: {
    bg: s.color.surface, border: s.color.border, radius: s.radius.container, shadow: s.shadow.resting,
  },
  input: {
    bg: s.color.surface, border: s.color.border, focusBorder: s.color.primary, radius: s.radius.interactive, text: s.color.text,
  },
  badge: {
    bg: s.color.secondary, text: s.color.onSecondary, radius: s.radius.pill,
  },
  productCard: {
    // most e-commerce-specific component — its own token group is worth having
    bg: s.color.surface, titleFont: s.font.display, priceFont: s.font.mono, radius: s.radius.container, shadow: s.shadow.resting,
  },
} as const;
```

---

## 3. Runtime injection — CSS variables

Convert the merged token tree into CSS custom properties once, at app root. Every
component then uses `var(--...)`, never a token import directly in its JSX styles —
this is what allows **runtime** theme swapping (e.g. previewing a client's theme
without a rebuild), not just build-time.

```typescript
// theme/generate-css-vars.ts
import { semantic } from './semantic';
import { components } from './components';

function flatten(obj: Record<string, any>, prefix = ''): Record<string, string> {
  return Object.entries(obj).reduce((acc, [key, value]) => {
    const varName = prefix ? `${prefix}-${key}` : key;
    if (typeof value === 'object' && value !== null) {
      Object.assign(acc, flatten(value, varName));
    } else {
      acc[`--${varName}`] = String(value);
    }
    return acc;
  }, {} as Record<string, string>);
}

export function cssVariables() {
  return { ...flatten(semantic, 'color', ), ...flatten(components, 'c') };
  // produces: --color-primary, --color-text, --c-button-primary-bg, --c-card-radius, etc.
}
```

```tsx
// theme/ThemeProvider.tsx
'use client';
import { createContext, useContext, useEffect } from 'react';
import { cssVariables } from './generate-css-vars';

const ThemeContext = createContext<Record<string, string> | null>(null);

export function ThemeProvider({ children, overrides }: { children: React.ReactNode; overrides?: Record<string, string> }) {
  const vars = { ...cssVariables(), ...overrides }; // overrides = per-tenant DB values, if you go that route

  useEffect(() => {
    const root = document.documentElement;
    Object.entries(vars).forEach(([key, value]) => root.style.setProperty(key, value));
  }, [vars]);

  return <ThemeContext.Provider value={vars}>{children}</ThemeContext.Provider>;
}

export const useTheme = () => useContext(ThemeContext);
```

Components then use the variables directly in Tailwind arbitrary values, or in plain CSS:

```tsx
<button className="bg-[var(--c-button-primary-bg)] text-[var(--c-button-primary-text)] rounded-[var(--c-button-primary-radius)] px-4 py-2 hover:bg-[var(--c-button-primary-bgHover)]">
  Add to cart
</button>
```

Or, cleaner: wire the same tokens into Tailwind config so you get real utility classes
instead of arbitrary-value soup.

---

## 4. Wiring into Tailwind

```typescript
// theme/generate-tailwind.ts
import { semantic } from './semantic';
import { components } from './components';

export const tailwindTheme = {
  colors: {
    primary: 'var(--color-primary)',
    primaryHover: 'var(--color-primaryHover)',
    background: 'var(--color-background)',
    surface: 'var(--color-surface)',
    text: 'var(--color-text)',
    textMuted: 'var(--color-textMuted)',
    border: 'var(--color-border)',
    danger: 'var(--color-danger)',
    success: 'var(--color-success)',
  },
  fontFamily: {
    display: 'var(--font-display)',
    body: 'var(--font-body)',
    mono: 'var(--font-mono)',
  },
  borderRadius: {
    interactive: 'var(--radius-interactive)',
    container: 'var(--radius-container)',
    pill: 'var(--radius-pill)',
  },
};
```

```javascript
// tailwind.config.js
const { tailwindTheme } = require('./theme/generate-tailwind');

module.exports = {
  theme: { extend: tailwindTheme },
};
```

Now components are written with real, semantic Tailwind classes that stay theme-aware:

```tsx
<button className="bg-primary hover:bg-primaryHover text-white rounded-interactive px-4 py-2">
  Add to cart
</button>
<h2 className="font-display text-3xl text-text">Featured products</h2>
<p className="font-mono text-lg text-primary">249 MAD</p>
```

This is the payoff: **the component code never changes between clients.** Only
`semantic.ts` (or the per-tenant override values) changes.

---

## 5. Per-client theme files (multi-client swapping)

Structure so each client is a self-contained theme file, not a set of scattered edits:

```
/theme
  /clients
    default.ts
    client-boutique-x.ts
    client-gym-y.ts
  active.ts    ← re-exports whichever client file `site.config.ts` points to
```

```typescript
// theme/clients/client-boutique-x.ts
import { primitives as p } from '../primitives';
import type { Semantic } from '../semantic';

export const semantic: Semantic = {
  color: {
    primary: '#8b1e3f',       // deep burgundy
    primaryHover: '#a5294f',
    onPrimary: '#ffffff',
    secondary: '#c9a227',      // gold accent
    onSecondary: '#1a1a1a',
    background: '#faf7f2',
    surface: '#ffffff',
    surfaceRaised: '#ffffff',
    text: '#1a1a1a',
    textMuted: '#6b6b6b',
    border: '#e5ded4',
    success: p.color.green[600],
    danger: p.color.red[600],
    warning: p.color.amber[600],
  },
  font: {
    display: '"Cormorant Garamond", serif',
    body: '"Inter", sans-serif',
    mono: p.font.family.mono,
  },
  radius: { interactive: '2px', container: '4px', pill: '9999px' }, // sharp corners = luxury feel
  shadow: { resting: p.shadow.sm, raised: p.shadow.md, overlay: p.shadow.lg },
};
```

```typescript
// theme/active.ts
import { activeThemeId } from '@/config/site.config';
import * as defaultTheme from './clients/default';
import * as boutiqueX from './clients/client-boutique-x';
import * as gymY from './clients/client-gym-y';

const themes = { default: defaultTheme, 'boutique-x': boutiqueX, 'gym-y': gymY };
export const semantic = themes[activeThemeId].semantic;
```

Switching a whole deployment's identity is now: change one string in `site.config.ts`.
No component touched.

---

## 6. Typography scale — detail

Don't just pick font families; define the full scale once so every heading/paragraph
in the app is consistent by construction, not by convention.

| Token | Size | Weight | Use |
|---|---|---|---|
| `display.hero` | 3rem / 48px | 700 | Homepage hero, campaign banners |
| `display.h1` | 2.25rem / 36px | 700 | Page titles |
| `display.h2` | 1.875rem / 30px | 600 | Section headers |
| `display.h3` | 1.5rem / 24px | 600 | Card titles, product names |
| `body.lg` | 1.125rem / 18px | 400 | Lead paragraphs |
| `body.base` | 1rem / 16px | 400 | Default body text |
| `body.sm` | 0.875rem / 14px | 400 | Captions, helper text |
| `mono.price` | 1.125rem / 18px | 600 | Prices — monospace so digits align in lists |
| `mono.sku` | 0.75rem / 12px | 400 | SKUs, order numbers |

Encode this as its own token group (`typography.ts`) mapping each named style to a
`{fontFamily, fontSize, fontWeight, lineHeight}` object, and expose it as a `<Text variant="h2">`
component rather than letting every component pick raw Tailwind text classes — this is
what prevents "every developer picks a slightly different heading size" drift over time.

```typescript
export const typography = {
  'display.hero': { family: 'display', size: '3rem', weight: 700, lineHeight: 1.1 },
  'display.h1':   { family: 'display', size: '2.25rem', weight: 700, lineHeight: 1.2 },
  'body.base':    { family: 'body', size: '1rem', weight: 400, lineHeight: 1.5 },
  'mono.price':   { family: 'mono', size: '1.125rem', weight: 600, lineHeight: 1.4 },
} as const;
```

```tsx
function Text({ variant, children }: { variant: keyof typeof typography; children: React.ReactNode }) {
  const t = typography[variant];
  return <span style={{ fontFamily: `var(--font-${t.family})`, fontSize: t.size, fontWeight: t.weight, lineHeight: t.lineHeight }}>{children}</span>;
}
```

---

## 7. Component variant system

To keep components swappable the same way plugins are, give each shared component a
**variant prop backed by tokens**, not a pile of conditional Tailwind classes scattered
through the codebase:

```tsx
// components/ui/Button.tsx
type Variant = 'primary' | 'secondary' | 'danger';

const variantClasses: Record<Variant, string> = {
  primary: 'bg-primary hover:bg-primaryHover text-white',
  secondary: 'bg-transparent border border-primary text-primary',
  danger: 'bg-danger text-white',
};

export function Button({ variant = 'primary', children, ...props }: { variant?: Variant } & React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button className={`rounded-interactive px-4 py-2 font-medium transition-colors ${variantClasses[variant]}`} {...props}>
      {children}
    </button>
  );
}
```

Every plugin and every client theme uses the same `<Button variant="primary">` — the
variant's actual appearance is entirely token-driven, so it re-skins automatically.

---

## 8. Accessibility & contrast rules (bake in, don't bolt on)

Enforce these as part of the token system, not as an afterthought per client:

- Every `on*` color (`onPrimary`, `onSecondary`) must maintain **4.5:1 contrast** against
  its paired background — check this whenever you write a new client theme file, ideally
  with a small script (`contrast-check.ts` using WCAG formula) that runs against
  `semantic.ts` in CI and fails the build if a client theme violates it.
- Define a `focus` token (`color.focusRing`) used identically on every interactive
  element — don't let per-client themes remove focus states for aesthetic reasons.
- Respect `prefers-reduced-motion` at the token/animation level, not per-component.

---

## 9. Full worked example: `tokens.ts` (the merged export)

```typescript
// theme/tokens.ts
import { primitives } from './primitives';
import { semantic } from './active';           // active client's semantic layer
import { components } from './components';
import { typography } from './typography';

export const tokens = { primitives, semantic, components, typography };
export type Tokens = typeof tokens;
```

This single `tokens` object is what `ThemeProvider` consumes, what `generate-tailwind.ts`
reads, and what any admin "theme editor" UI (if you build one later) would read/write to
let a non-technical client tweak their own colors from a settings page instead of you
editing a file by hand.

---

## 10. Where this connects to the system design doc

- Plugins register their own component tokens the same way they register hooks —
  a `loyalty-points` plugin can extend `components.ts` with `components.loyaltyBadge`
  without touching the core token file.
- The **slot system** (see System Design §6) renders plugin UI into named positions;
  every component rendered into a slot should consume tokens the same way core
  components do, so a plugin's UI never looks "bolted on" against a client's theme.
- Per-client deployment (System Design §8) pairs naturally with per-client theme files
  here: one `site.config.ts` entry picks both the plugin set *and* the theme file for
  that deployment.
