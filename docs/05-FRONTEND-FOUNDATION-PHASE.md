# Frontend Foundation Phase — Consolidated Instructions

This doc covers four related tasks, in order. Read the whole thing before
starting Task 1 — later tasks depend on decisions made in earlier ones.

---

## Task 1 — One-time copy of generic UI primitives from the e-commerce project

Copy these files literally, as a one-time act — once copied, they're fully
this project's own code, no future session needs to reference the source
project again (consistent with this project's established self-contained rule):

- `PrimaryButton.tsx`, `SecondaryButton.tsx`, `TextInput.tsx`,
  `InputLabel.tsx`, `Checkbox.tsx` — already fixed to use theme tokens, zero
  e-commerce-specific logic
- `GuestLayout.tsx`'s background fix (`bg-gray-100` → `bg-background`)

Do NOT copy: anything cart/checkout/product-specific, or the header/footer
components wholesale — this project's header/footer content is genuinely
different (vehicles, not products) and gets built fresh in Task 3, using the
copied primitives as building blocks.

Verify each copied component renders correctly here before moving on.

---

## Task 2 — Documentation completeness audit

Before building anything new, check whether this project's own documentation
is actually accurate:

1. Go through every phase's summary since Phase 1 and confirm `CLAUDE.md` and
   `docs/event-registry.md` genuinely contain what each phase claimed to add
   — real sections with real content, not just referenced as done.
2. Specifically assess: is `add-plugin` still the only skill file needed, or
   has a real, repeated pattern emerged that deserves its own skill? The
   core-owned-filter pattern (used for driver-eligibility checking and
   elsewhere, to let two plugins interact without importing each other) has
   now come up more than once — consider whether "how to add a core-owned
   cross-plugin filter" deserves its own skill file, distinct from
   `add-plugin`'s general plugin-scaffolding scope.
3. Report findings before writing anything new — if a new skill is
   genuinely warranted, write it after this audit confirms it, not
   speculatively.

---

## Task 5 — The frontend navigation + homepage phase

This is the main phase, closing the HIGH-priority findings from the
reachability audit: no shared header/nav/footer exists anywhere on the
storefront, the fleet listing is unreachable from any real navigation path,
and `/` still shows Laravel's default Welcome scaffold.

### Use Stitch properly — download the HTML export, don't just screenshot the canvas

**Whenever pulling a design from Stitch via MCP: download the actual HTML
export for the screen, don't rely on a canvas screenshot alone.** A raw
screenshot loses exact spacing, color values, and structural detail that the
real exported HTML preserves — the HTML is the source of truth for translating
a design into actual tokenized components. Take screenshots for quick visual
reference, but treat the downloaded HTML as the thing to actually read
spacing/structure/color values from. Confirm the relevant Stitch screens
(header, footer, homepage layout, and any others in the project) actually
exist before assuming they do.

### What to build

1. **One real, fixed customer-facing layout** (not a swappable
   `LayoutVariantRegistry` region — that was deliberately not built here,
   one real theme doesn't need swappable layouts): a header (logo, a real
   link to `/vehicles`, an account/booking-history link, a link to
   driver-verification if a user has an incomplete one — see the
   reachability audit's finding #4) and a footer.
2. **Wire this layout onto every single storefront page**: `Vehicles/Index`,
   `Vehicles/Show`, `Bookings/Checkout`, `Bookings/Payment`, `Bookings/Show`,
   `DriverVerification/Show`. Every one of these currently has zero shared
   navigation — confirm each individually after wiring, don't assume
   consistency from a couple of spot checks.
3. **Decide and build the homepage.** Replace Laravel's Welcome scaffold at
   `/` with something real — either the vehicle listing directly, or a proper
   landing page (hero, featured vehicles) if Stitch has a homepage screen
   designed. Use the Stitch homepage screen if one exists; if not, a direct
   redirect/render of the vehicle listing at `/` is an acceptable, simpler
   choice — state which you're doing and why.
4. Use the primitives copied in Task 1 as the building blocks — don't
   reinvent buttons/inputs while building this.

### Verification

Click through the entire real customer journey and confirm every single step
has working navigation, not just that a header component exists somewhere:
`/` → fleet listing → a vehicle detail page → checkout → payment → booking
confirmation → (as a logged-in user) profile/booking history. Confirm the
header/footer render identically and correctly across every one of those
pages, not just the ones that happened to get checked first.

---

## Task 4 — Admin-driven theme management (the real "one centralized place")

**What exists today is not yet the full centralized system.** Phase 3 of this
project only ported the *file-based* theme layer (colors/fonts live in a
`.ts` file, switching themes means editing a file + an `.env` variable). The
e-commerce project later built a genuinely centralized admin system on top
of that — upload a theme as JSON via Filament, activate it, zero rebuild —
and that's what "one place to change everything" actually means. Port that
system now, since it's a one-time, proven, fully domain-agnostic copy
(colors/fonts/radius/shadow tokens don't know or care whether they're
theming cars or products):

- `Theme` model + migration (a `themes` table: id, name, slug, `data` JSON,
  `is_active`) — same shape as the e-commerce project's
- `ThemeManager` (activate/resolveActive, transactional single-active-row
  swap)
- `ThemeSchemaRegistry` (validates an uploaded theme's JSON against the
  expected token shape, extensible so a future plugin could add its own
  token field)
- `ContrastChecker` (WCAG contrast validation — warn on save, hard-confirm-
  required on activation, never silently allow an illegible theme live)
- A Filament `ThemeResource`: upload a JSON file, see a swatch/font preview,
  activate it — same admin experience as the e-commerce project
- Wire `HandleInertiaRequests` to share the *resolved theme data* (not just
  a theme name) as an Inertia prop, and update `ThemeProvider` to consume it
  directly — this is the part that makes activation take effect with zero
  rebuild, matching the e-commerce project's approach exactly

Seed this project's existing file-based themes (`default.ts` and
`client-demo-rentals.ts`... or whatever it's actually named — confirm) as
the first two rows in the new `themes` table, so nothing that already works
breaks when this lands.

**Font tokens go through this same centralized system**, not a separate
mechanism — see Task 4b below for the actual font choice, but it gets set
via `semantic.ts`/a seeded theme row, same as every other token.

### Task 4b — Font selection

Set these font tokens as part of seeding the default theme above:

```typescript
font: {
  family: {
    spaceGrotesk: '"Space Grotesk", sans-serif',
    inter: '"Inter", sans-serif',
    jetbrainsMono: '"JetBrains Mono", monospace',
  },
},
```

```typescript
font: {
  display: p.font.family.spaceGrotesk,  // headings, hero text
  body: p.font.family.inter,             // paragraphs, UI, forms
  mono: p.font.family.jetbrainsMono,      // prices, license plates, booking reference numbers
},
```

**Why this pairing**: Space Grotesk has a modern, slightly technical/premium
character that reads well for an automotive/travel context (distinct from
generic sans-serifs, without going as ornate as a luxury serif) — a good
match for a rental business serving international travelers who expect a
clean, trustworthy, modern-feeling site. Inter is already proven highly
legible for body text and forms. JetBrains Mono for anything with aligned
digits (prices, license plates, booking reference codes) matches the exact
reasoning already used for this in the e-commerce project's token system.

Both fonts are free, real Google Fonts — load them via a `<link>` tag in the
Blade view (same as the e-commerce project), and confirm they're actually
loading (check the rendered page uses them, not a fallback system font)
before calling this done.

---

## Build order

1. Task 1 (copy primitives) — quick, low-risk, do first
2. Task 2 (documentation audit) — quick, report findings before building anything new
3. Task 4 + 4b (admin-driven theme system + font tokens) — port the
   centralized theme management system, seed it with the existing themes
   plus the new font choice
4. Task 5 (the frontend navigation phase itself) — the main work, verify
   thoroughly per its own verification section above
