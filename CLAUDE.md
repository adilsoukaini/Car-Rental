# CLAUDE.md

Read automatically by Claude Code at the start of every session in this repo.
Source of truth for how this project is architected. This file will grow as
phases are built — every phase that establishes a real, reusable pattern gets
a section added here, written after the pattern is proven, not before.

## What this project is

A custom car rental platform, architected like the prior e-commerce build:
small stable core, every feature (fleet, booking, payments, driver
verification, locations) an independent plugin registering into core via
Events/Listeners and Pipeline filters. Frontend fully re-themeable per client
from a single token file.

Full architecture in `docs/01-SYSTEM-DESIGN.md`, domain requirements in
`docs/03-DOMAIN-REQUIREMENTS.md`, design system in `docs/02-DESIGN-SYSTEM.md`,
working process in `docs/PROCESS-GUIDE.md`. Read `PROCESS-GUIDE.md` first —
it governs how every phase gets built and verified, not just what gets built.

## Mobile companion app

A React Native (Expo) mobile app lives in `../car-rental-mobile/`
(`github.com/adilsoukaini/car-rental-mobile`). It is a **pure frontend API
consumer** — the same Laravel JSON APIs that power the Inertia web frontend
serve the mobile app with zero backend changes. Architecture docs in
`../car-rental-mobile/docs/`, following the same numbered-MD + PROCESS-GUIDE
discipline as this project.

Key facts:
- **Stack**: Expo SDK (React Native) + TypeScript + NativeWind + Expo Router
- **API client**: Single module (`lib/api.ts`) — typed, centralized, auth-aware
- **Theme tokens**: Ported directly from `resources/theme/` (identical values,
  NativeWind delivery instead of CSS custom properties)
- **Testing**: Maestro (mobile E2E, equivalent of Playwright) — YAML flows,
  screenshot verification, real emulator/device
- **Build/Deploy**: EAS Build + EAS Submit (cloud builds, no local Xcode needed)
- **Auth tokens**: `expo-secure-store` (encrypted), never `AsyncStorage`
- **Hard rule**: The backend is immutable from mobile work. If an API gap is
  found, flag it — never silently add endpoints or change backend behavior from
  the mobile repo.

## Available MCP servers

This project has access to these MCP (Model Context Protocol) servers:

| MCP Server | Purpose | Status |
|---|---|---|
| **playwright** | Web browser automation (screenshots, clicks, form fills, console checks) | ✔ Connected |
| **scrapling** | Web fetching (stealthy browser, bulk GET, screenshots via session) | ✔ Connected |
| **maestro** | Mobile UI automation (write/run YAML flows, control emulators, inspect view hierarchy, take screenshots) | ✔ Connected |
| **mobile-agent** | React Native / Expo testing (Metro management, `run_flow_with_context`, tail logs, reload app) | ✔ Connected |
| **stitch** | UI design generation (screens from text, variants, design systems) | ✔ Connected |

### Maestro MCP (`maestro`)

The mobile equivalent of Playwright MCP. Bundled inside the Maestro CLI:

```bash
# Installed at /root/.maestro/bin/maestro (v2.8.0)
maestro mcp              # Start MCP server
maestro test .maestro/   # Run flows directly
```

Claude Code can: write Maestro YAML test flows, run them on the Android
emulator (`Flutter_Emulator`, API 33), take screenshots, inspect view
hierarchy CSV to debug failing selectors, and validate flow syntax.

### Mobile-agent MCP (`mobile-agent`)

Expo/React Native-aware wrapper around Maestro. Adds Metro bundler awareness,
log tailing, and combined flow+logs+screenshot+diagnosis execution:

```bash
npx -y mobile-agent-mcp    # Start MCP server
```

Use `maestro` for general mobile testing; use `mobile-agent` when you need
Expo-specific context (Metro status, reload, RN diagnosis).

## Hard rules (do not violate these)

1. **Core never imports a plugin.** Core code has zero references to
   anything in `/plugins`.
2. **Plugins never import other plugins directly.** Cross-plugin
   communication goes through Events, the Pipeline filter system, Slots, or a
   declared dependency resolved through config — never a raw class reference.
3. **Components never hardcode colors, fonts, spacing, or radius.**
   Everything visual comes from theme tokens.
4. **New pages expose extension points via Slots**, not hardcoded plugin
   references.
5. **Every new Event/Filter/Slot gets documented in `docs/event-registry.md`**
   the moment it's added — this is the internal API contract every plugin
   depends on.
6. **Database:** shared fields go in core migrations. Feature-specific data
   goes in a plugin's own migrations, or a `metadata: json` column on core
   models for small additions.
7. **Before running any migration against a real database, show the exact
   SQL/schema and get explicit approval first.**
8. **Any page/grid rendering multiple items must batch-load per-item data in
   one query** — never one query per item. This was the most repeated bug
   class in the prior build; catch it at design time, not in review.
9. **Availability-overlap logic (booking date ranges) gets explicit test
   coverage before being trusted** — a bug here double-books a real vehicle,
   which is a worse failure than almost any other bug class in this project.
10. **Never declare work "done" without actual verification.** For backend
    changes: real HTTP requests (curl or Playwright) with real DB data, exact
    numbers verified, not assumed. For frontend changes: Playwright screenshots
    with zero console errors, every page loaded and visually checked. Tests
    passing is necessary but not sufficient — tests proved the ToastContainer
    outside the Inertia tree was "fine" (220/220) while the real browser
    crashed on every page load. The build (`npm run build`) must succeed and
    the dev server must be serving the rebuilt assets before verification
    begins. If you haven't loaded the page in a browser after the last change,
    the task is not done.
11. **Every admin→frontend connection must be tested end-to-end.** If a feature
    has an admin control (theme activation, layout variant switching, plugin
    toggle, site identity save), the full round-trip must be verified: (1) make
    the change in the admin panel via Playwright, (2) load the storefront in a
    separate browser context or navigation and confirm the change took effect,
    (3) verify zero console errors on both sides. "The admin page loaded without
    errors" is not sufficient — the point of an admin control is that it changes
    what visitors see.
12. **After any frontend feature is completed, run all Playwright test scripts**
    (`customer-flow.ts`, `admin-flow.ts`, `customer-journey.ts`, `smoke-test.ts`)
    to verify nothing was broken. These scripts are regression guards — a change
    to checkout shouldn't silently break the fleet page. If a script fails,
    investigate and fix before declaring the feature done. **Every new feature
    gets its own Playwright test script** (e.g. `.claude/playwright-tests/
    {feature-name}.ts`) verifying its specific behavior end-to-end. **After
    using Playwright MCP, run `bash scripts/cleanup-artifacts.sh`** to remove
    session logs, snapshots, screenshots, and root-level image artifacts that
    accumulate during interactive testing.

## Folder structure

```
/app/Core            kernel only — FilterRegistry, SlotRegistry, PluginManager, core Events/Contracts
/app/Models            core domain models (User, Vehicle, Booking, Location) — conventional
                       Laravel namespace, NOT under app/Core. "Core owns these" refers to
                       who owns the data, not a literal namespace instruction. Confirmed
                       2026-08-03: this matches what the prior e-commerce build actually did.
/app/Http/Controllers  core-owned controllers rendering Inertia pages
/plugins               every feature, one local Composer package per plugin
/resources/js/Pages    Inertia pages
/resources/theme       token system
/config                plugins.php, site.php
/database/migrations   core migrations only
/docs                  architecture docs, event registry
```

**Environment note:** the default `php`/`composer` on this machine resolve to
PHP 7.4, which is below Laravel's minimum. Use `/usr/local/bin/php8.4`
explicitly (a PATH shim at `/tmp/php84bin` was set up in-session; it may not
survive a shell restart — recreate it or invoke the binary path directly).

## When adding a new feature

Use the `add-plugin` skill (`.claude/skills/add-plugin/SKILL.md`) rather than
improvising package structure each time.

## Code style

- PHP: PSR-12. TypeScript strict mode, no `any` except at genuine framework
  boundaries.
- Prefer small, single-responsibility files.
- Every plugin should be disable-able and eventually deletable without
  breaking anything else.

## Before starting a big change

State which phase you're working on and confirm the plan against the docs if
there's any ambiguity, rather than silently deviating.

---

*(New sections get added below this line as real patterns are proven —
payments, booking/availability engine, layout variants, etc., each written
after the phase is verified working, per `PROCESS-GUIDE.md` rule 10.)*

## Phase 2 — Kernel (verified 2026-08-03)

- `FilterRegistry`, `SlotRegistry`, `PluginManager` (`app/Core/Support/`), the
  `Role` enum (`app/Enums/Role.php`), and the `Plugin` model ported unchanged
  from the prior e-commerce build — fully domain-agnostic. `PluginManager::boot()`
  is wired into `AppServiceProvider::boot()`. `config/plugins.php` starts with an
  empty `registry` array — the first plugin fills it in (Phase 4).
- **`HasMinimumRole` deliberately NOT copied yet** — it's a role-gating trait for
  Filament Resources/Pages; copying it with no Filament panel would leave dead
  code Larastan flags as an unanalyzed unused trait. Copy it back alongside the
  first Filament Resource that actually needs role-gating. (Deferral is cheap
  here — the file is trivial to add back.)
- Core domain Events (`BookingRequested`, `BookingConfirmed`, `BookingCancelled`,
  `VehicleCheckedOut`, `VehicleReturned`, `DamageReported`, `DriverVerified`,
  `PaymentCaptured`) live in `app/Core/Events/`, documented in
  `docs/event-registry.md` with the (not-yet-registered) named filters/slots.

## Phase 3 — Theme engine (verified 2026-08-03)

- Three-tier token system ported unchanged: `primitives.ts` → `semantic.ts` →
  `components.ts` → `tokens.ts`; one domain adaptation: `components.productCard`
  renamed `components.vehicleCard`.
- `ThemeProvider` wraps the Inertia `<App>` in `app.tsx`, fed by
  `resources/theme/active.ts`, which picks a theme from `window.__THEME__`
  (injected by `app.blade.php` from `config('site.active_theme')`, itself from the
  `ACTIVE_THEME` env var). Theme swap is a zero-rebuild runtime operation.
- **`resources/theme/clients/client-swap-proof-DISPOSABLE.ts` is NOT a real
  client** — a second data point to prove the swap mechanism; delete it (and its
  `active.ts` entry) once a real second client theme exists. "Demo Rentals" is
  not onboarded client data.
- LESSON: `tailwind.config.js` content globs must match actual file extensions
  (`*.{ts,tsx}` across `resources/js` and `plugins/**/resources/js`) — stale
  globs (e.g. leftover `*.jsx`) silently purge utility classes from compiled CSS.
- A throwaway `/theme-test` route + `Pages/ThemeTest.tsx` proves token-driven
  Tailwind classes (`bg-primary`, `rounded-interactive`); remove when redundant.

## Phase 4 — First plugin: fleet/vehicle catalog (verified 2026-08-03)

- `Vehicle` is a **core model**; the public storefront (listing + detail) is a
  real Composer plugin at `plugins/fleet-management/`, wired via a `path`
  repository in root `composer.json` + `config/plugins.php`'s `registry` array
  (same split as the source project's `catalog` plugin: core owns the model,
  the plugin owns storefront controller/routes/pages).
- **Filament v3 does not support Laravel 13** (`illuminate/auth` tops out at
  `^12.0`). Installed **Filament v4**, API restructured: `Filament\Schemas\Schema`
  replaces `Filament\Forms\Form`; resources live under
  `App\Filament\Resources\{Model}\` with separate `Schemas/{Model}Form.php` +
  `Tables/{Model}Table.php`. Do NOT copy v3 resource code verbatim — generate a
  v4 stub with `php artisan make:filament-resource`, then port field-by-field.
- `EnsureAdminPanelAccess`: write a full `handle()` implementation — v4's
  `Authenticate` middleware `authenticate()` signature/flow doesn't translate.
- **Genuinely toggleable** (proven via real process-per-request boots, NOT
  PHPUnit): disabled in `plugins` table → `/vehicles` 404; `activate()` → 200;
  `deactivate()` → 404. `scripts/verify-plugin-toggle.sh [slug] [path]` automates
  disable→404→enable→200→disable→404 and restores enabled afterward. Re-run it
  after any change to `PluginManager`, `AppServiceProvider`, or plugin routes —
  don't trust memory of a past manual check.
- TEST LIMITATION (persistent across the project): PHPUnit boots the app
  (`PluginManager::boot()`) *before* `RefreshDatabase` migrates, so the `plugins`
  table never exists at boot — the provider is never auto-registered in tests.
  Toggle is only testable via real process-per-request testing. Tests instead
  register the plugin's ServiceProvider directly in `setUp()` and use literal
  paths (`/vehicles`) — `route()`/`Route::has()` don't see routes registered
  post-boot (a `UrlGenerator` caching quirk, test-harness-only; real HTTP
  dispatch works).

## Phase 5 — Booking/availability engine (verified 2026-08-03)

Highest-scrutiny code per rule 9 (a bug double-books a real car). Three
decisions confirmed before building (mechanism: `booking.availabilityCheck` in
`docs/event-registry.md`):

1. **Exclusive-end boundary, no turnaround buffer.** A booking ending at 10:00
   and another starting at 10:00 on the same vehicle do NOT overlap. A buffer is
   deliberately NOT in the core query — it bolts on as a second pipe later.
   **Flagged in `docs/03-DOMAIN-REQUIREMENTS.md` as a must-configure-before-
   real-launch item**, not a someday-maybe.
2. **Only `confirmed` and `checked_out` bookings block** (`pending` and
   `cancelled` do not). Two `pending` requests for the same vehicle/dates can
   coexist — safety at confirm time comes from `BookingCreator`'s lock, not the
   overlap query. (NOTE: Phase B later revises this — see the deposit-gate
   section.)
3. **Strict location matching** — a vehicle is bookable only at its current
   `location_id`, updated automatically by `RelocateVehicleOnReturn` when
   `VehicleReturned` fires (how a one-way rental's car "belongs" at the drop-off
   afterward). No staff-override path exists yet (deferred as its own feature).

- `BookingCreator` is the only sanctioned way to create a `confirmed` booking; it
  wraps availability recheck + insert in a DB transaction with
  `Vehicle::lockForUpdate()`, so concurrent confirm attempts serialize (second
  one's recheck sees the first's committed booking and fails). Verified with 14
  automated tests + real `tinker` round-trips.
- Honest limitation carried forward: SQLite has no true row-level locking
  (serializes at DB level). Real concurrent-connection proof deferred until a
  row-locking DB exists — later closed with Postgres (see DB section).

## Phase 6 — Pricing engine (verified 2026-08-03)

- Scope deliberately narrow: base daily rate × duration + duration discount
  tiers + deposit computation, via `booking.priceCalculation` (documented in
  `docs/event-registry.md`). **Explicitly NOT built:** Extras (need their own
  data model — a real second feature, rule 6) and actually charging money
  (separate Payment phase — this phase only computes numbers).
- Business-policy decisions (tuning knobs in
  `plugins/booking-engine/config/booking-engine.php`, cheap to change):
  1. **Cliff/threshold discounts**, not graduated — hit 7 days → whole booking
     10% off; hit 30 → 25% off. Not cumulative across tiers.
  2. **Partial days round up** to the next full day, minimum 1 day.
  3. **Deposit = flat percentage of the discounted subtotal**
     (`deposit_percentage_of_subtotal`, currently 20%), not per-category.
- `BookingCreator` now computes `total_price` and `security_deposit_amount`
  internally via `booking.priceCalculation` and **ignores any caller-supplied
  values** for both (closes a Phase 5 gap; a test passes a bogus `total_price`
  and confirms the persisted value is the correctly computed one).
- Verification standard: exact hand-computed totals at boundaries (7 days at
  100/day → 630.00), not "some discounted amount".

## Phase 7 — Payments (verified 2026-08-03)

- `PaymentGatewayRegistry` + `PaymentGateway` contract in `app/Core/{Support,Contracts}`;
  `payments-stripe` implements it. Mechanism in `docs/event-registry.md`'s
  "Payment Gateways" section.
- **Interface NOT ported verbatim**: e-commerce's Checkout Sessions capture
  immediately (no hold-now-decide-later). Here: **PaymentIntents API with
  `capture_method: manual`**. Interface has authorize/capture/release alongside
  charge/refund.
- **CMI deliberately not built** — it has no hold/pre-auth concept (refund
  unimplemented). Stripe is the priority gateway (business is primarily
  international-customer-facing).
- LESSON (serious bug found by running the app): `StripeGateway` must build
  `StripeClient` **lazily (`??=`) on first use**. Eager construction ran on every
  request at provider boot, so an empty `STRIPE_SECRET` took down the entire site
  (even `artisan tinker`), not just payment pages. Same "core/plugin must not
  hard-crash the whole site over one optional thing" principle recurs (see
  driver-verification middleware guard).
- Verified for real against `api.stripe.com` with test-mode keys (`sk_test_`/
  `pk_test_`, reused from e-commerce `.env`; Mailtrap sandbox mail copied the
  same way): a real `PaymentIntent` with `capture_method: manual`, confirmed
  amount 90000, currency `mad`, metadata `booking_id`/`payment_type`; after
  release, Stripe's record shows `status: canceled`.
- Event shape changes: `PaymentCaptured` now carries the real `Payment` model
  (nothing consumed the old shape). Added `PaymentAuthorized`, `PaymentFailed`,
  `PaymentReleased` — a release cancels a hold with no money moved; a refund
  reverses money that was captured.
- `bootstrap/app.php` must keep the CSRF exclusion for `webhooks/*`.
- Business/legal (not code): Stripe requires the account-holding business to be
  domiciled somewhere Stripe operates — see `docs/03-DOMAIN-REQUIREMENTS.md`'s
  Payment section and the `stripe_entity_status` memory. A foreign entity is
  already in motion; re-confirm at go-live.

## Locations admin CRUD (verified 2026-08-03)

- Pattern: schema + availability logic + one-way relocation were real and tested,
  but **no admin CRUD existed** — every `Location` row was created by factory or
  `tinker`. Caught by searching for a `LocationResource`/routes/pages and finding
  nothing. Added `App\Filament\Resources\Locations\LocationResource` (name,
  address, city, country, lat/lng, `is_active` toggle; same shape as
  `VehicleResource`).
- **`Location.is_active` (on the schema since Phase 1) was never wired into
  anything** — now wired into `CoreAvailabilityCheckPipe`: an inactive location
  blocks new bookings requesting pickup there. Decision: `is_active` is a
  soft-disable for **future** bookings only — existing confirmed bookings remain
  valid (same precedent as `Vehicle.status` not retroactively touching bookings).
- LESSON (unrelated, not a bug): nested `Model::factory()` references inside a
  factory's `definition()` are eagerly evaluated and persisted even when the
  caller overrides that field in `create([...])`. Harmless under `RefreshDatabase`
  (wiped every test) but produces orphaned rows on the persistent dev DB — clean
  up after `tinker` verification; don't assume an override prevented the nested
  factory row.

## Bookings admin CRUD (verified 2026-08-03)

- Pattern: `Payment::captureDeposit()`/`releaseDeposit()`/`refund()` (Phase 7)
  had **no real caller anywhere** — modeled, tested, unreachable in production.
- Added `App\Filament\Resources\Bookings\BookingResource` — **deliberately no
  create/edit pages**: a `Booking` must only be created via
  `Plugins\BookingEngine\Support\BookingCreator` (enforces availability, computes
  price); an admin form setting arbitrary dates/prices would reopen the
  raw-caller-supplied-price gap. List + View only.
- View page gives Release/Capture Deposit their first real callers: staff actions
  gated on an active `deposit_authorization` `Payment`. Deliberately manual (not
  automatic on `VehicleReturned`) — deciding release-vs-capture requires a human
  inspecting the vehicle.

## Phase 9 — Driver verification (verified 2026-08-04)

- **Cross-plugin constraint**: `driver-verification` and `booking-engine` are
  separate plugins; `BookingCreator` needs an eligibility answer only
  driver-verification can give. Neither may reference the other (rule 2), so the
  check is a **core-owned DTO**: `App\Core\Support\DriverEligibilityCheckRequest`
  — the canonical pattern for "a DTO in core that both plugins depend on without
  depending on each other."
- Decisions resolved before building: verification is **per-User** (not
  per-Booking — per-booking needs an async review step + a pending workflow that
  doesn't exist); **guest bookings are exempt** (enforcing it would end true
  guest checkout or require the pending workflow — deferred).
- **Age eligibility evaluated at the booking's `pickup_at` date, not "today"** —
  a driver who turns 21 before pickup is eligible. Tested at the exact boundary.
- Plugin-owned Filament resource pattern: `AdminPanelProvider` only auto-discovers
  `app/Filament/Resources`; a plugin's `DriverVerificationResource` lives inside
  the plugin and registers itself into `Filament::getDefaultPanel()` from its own
  `ServiceProvider::boot()`. Core never references the plugin's namespace.
- Plugin-owned data goes in the plugin's own migrations
  (`plugins/{slug}/database/migrations/` + `loadMigrationsFrom()`) — rule 6.
  Laravel tracks migrations by filename, not path, so moving a ran migration is
  safe.
- TEST LIMITATION: `RefreshDatabase` migrates only paths known before a test
  manually registers a plugin — run plugin migrations explicitly
  (`$this->artisan('migrate', ['--path' => ...])`). The `route()`/`UrlGenerator`
  quirk also hits Filament's own internal rendering and `redirect()->route()` in
  tests (test-harness-only; real production pre-registers everything).

## Booking confirmation email (verified 2026-08-04)

- Pre-flight caught another "modeled but never consumed" case: `BookingConfirmed`
  existed since Phase 2 but was never dispatched — and `docs/event-registry.md`
  described a two-step request→hold→confirm flow that was never built.
  `authorizeDeposit()` had zero callers in the booking flow.
- Resolved by dispatching reality: `BookingConfirmed::dispatch($booking)` now
  fires at the end of `BookingCreator::create()` — immediate confirm, no payment
  gate. Docs corrected to describe the real one-step flow; deposit-gated
  confirmation named as a real, undesigned future decision.
- Email is **self-contained** (no public booking-detail page existed yet):
  vehicle, dates/locations, total, deposit inline, no link. A public
  `bookings.show` page flagged as its own deferred item (later built — see
  booking-history section).
- Mechanism: `App\Core\Listeners\SendBookingConfirmationEmail` — a plain core
  listener registered via `Event::listen()` in `AppServiceProvider::boot()`
  (**no dedicated `EventServiceProvider` exists** in this project; plugin
  listeners register the same way, e.g. `RelocateVehicleOnReturn`). Recipient:
  `$booking->guest_email ?? $booking->user?->email`, no-op if neither.
  `App\Mail\BookingConfirmation` is a queued `Mailable` rendering
  `resources/views/emails/booking-confirmation.blade.php` — **plain inline CSS,
  not theme tokens** (email clients can't consume CSS custom properties). Both
  implement `ShouldQueue`; the `jobs` table + `QUEUE_CONNECTION=database` were
  already in place.
- Environment gap (not a code defect): this sandbox's PHP CLI has **no `php.ini`
  loaded** (`php --ini` reports "(none)"), so `openssl.cafile` is unset and
  Mailtrap TLS fails even though the system CA bundle at
  `/etc/ssl/certs/ca-certificates.crt` is valid. Fix: run with
  `-d openssl.cafile=/etc/ssl/certs/ca-certificates.crt`. Worth fixing at the
  environment level in a later phase.

## Kernel fix: FilterRegistry/SlotRegistry static-state accumulation (verified 2026-08-04)

- Root cause: `FilterRegistry::$pipes` and `SlotRegistry::$slots` are `static`
  arrays; Laravel boots a new `Application` per test method, re-running every
  provider `boot()`, and nothing ever cleared the statics — entries accumulated
  across a test run (measured `pipesFor('booking.priceCalculation')`: 2→4→6).
- Why masked: existing pipes recompute their result from scratch from the
  immutable `$breakdown->request`, so running the same pipe 2/4/6 times produced
  identical numbers by coincidence; boolean availability/eligibility pipes were
  equally immune by accident. **Fragile, not safe** — the first non-re-derivative
  pipe (e.g. a promo pipe decrementing a counter) would silently double-apply.
- Not a production bug today (no Octane; each PHP-FPM request is a fresh process)
  but would resurface silently under any persistent-worker model.
- Fix: `flush()` added to both registries (clears the static array), called at
  the top of `PluginManager::boot()` — every boot starts from a clean registry.
  No public API change.
- LESSON: any shared static/kernel state deserves an explicit "does this reset
  across the process lifecycles that matter" check the first time something real
  depends on it.

## Booking history + the deferred confirmation-page gap (verified 2026-08-04)

- Pre-flight (reading real source, not assuming): the source project's
  `RecentOrders` is a **Dashboard-slot widget** (`SlotRegistry::register(
  'account.dashboardWidgets', ...)`) rendered from `ProfileController::edit()`
  into `Profile/Edit.tsx` — NOT a dedicated history page, and NOT hosted in
  `Dashboard.tsx` (which is an untouched Breeze stub). It links to the same
  `orders.confirmation` page the guest email uses.
- Built: `resources/js/pluginComponentRegistry.tsx` (the `SlotOutlet` mechanism —
  didn't exist here before, ported from source), `Widgets/BookingHistory.tsx`,
  the `SlotRegistry::register()` call in `AppServiceProvider::boot()`,
  `ProfileController::edit()` batch-loads the user's last 5 bookings
  (`vehicle`/`pickupLocation`/`returnLocation` in one query, rule 8).
  `App\Http\Controllers\BookingController` (core-owned, mirrors
  `OrderConfirmationController`'s `isOwner || hasValidSignature` gate) +
  `bookings.show` route + `Bookings/Show.tsx` close the public booking-detail
  gap. `SendBookingConfirmationEmail` now computes a real `confirmationUrl`
  (signed for guests, plain route for owners).
- Theming scope: `AuthenticatedLayout.tsx` + `Profile/Edit.tsx` wrapper markup
  retokenized. **Known pre-existing rule-3 violations left flagged, not fixed:**
  `resources/js/Components/{NavLink,Dropdown,ResponsiveNavLink}.tsx` and
  `Profile/Edit.tsx`'s three Partials (`UpdateProfileInformationForm`,
  `UpdatePasswordForm`, `DeleteUserForm`) still have hardcoded indigo/gray
  Tailwind classes — a named deferral for a future theming sweep.
- Verification note: signed URLs bind the full host into the HMAC — generate and
  validate them with a matching `APP_URL`/real-request host, or every check is a
  guaranteed false negative.

## Status-only booking cancellation (verified 2026-08-04)

- Pre-flight: `BookingCancelled` had zero dispatch sites. **Also found:
  `VehicleCheckedOut`/`VehicleReturned` have zero dispatch sites** — no real
  checkout/return lifecycle exists at all (damage-reporting correctly pulled out
  to wait on that lifecycle).
- Cancellation policy is refund logic, but there's no real money to apply it to:
  `authorizeDeposit()` has no callers, so no `authorized` deposit `Payment` row
  is ever created and Release/Capture Deposit are permanently invisible. Three
  discoveries (refund math, deposit-release visibility, deposit-capture
  visibility) all root back to the one undesigned deposit-gate decision.
- **Ships status-only**: `ViewBooking`'s "Cancel Booking" action (visible when
  `status === 'confirmed'`) sets `status = 'cancelled'` and dispatches
  `BookingCancelled` — first real dispatch site. No refund computation, no
  cancellation email (both future additions, named in `docs/event-registry.md`).
  Freeing the vehicle needed zero new logic: `CoreAvailabilityCheckPipe`'s
  blocking statuses (`confirmed`, `checked_out`) already excluded `cancelled`.
- Verified: a real booking cancelled via the exact action code, then the same
  vehicle/dates genuinely became bookable again.

## The real booking-creation flow (verified 2026-08-04)

- **The most significant "modeled but never consumed" finding**: `BookingCreator`
  had **zero real callers** anywhere outside `tinker`/tests. `Vehicles/Show.tsx`'s
  "Book this vehicle" button had no `onClick`/`href` — a dead Phase 4 placeholder.
  Nine-plus phases were verified against a booking path that only existed in
  `tinker`.
- Also corrected the deposit-gate framing: a real hold structurally requires a
  **client-side step** (`payment_intent.amount_capturable_updated` only fires
  after the customer confirms via Stripe Elements, possibly 3DS) — "sync vs async
  backend call" was never the real question.
- Split: **Phase A** (this) builds the real public checkout with zero change to
  `BookingCreator`'s behavior (still immediate-confirm, no gate). **Phase B**
  (Stripe Elements + real hold + cancellation refund math + invisible admin
  deposit buttons) deferred until A is proven.
- Built: `Plugins\BookingEngine\Http\Controllers\BookingCheckoutController`
  (`GET/POST /vehicles/{vehicle}/book`, registered from `BookingEngineServiceProvider`
  — not core, since it must call `BookingCreator`), `Bookings/Checkout.tsx`
  (price preview + guest/owner contact form), real date-picker form on
  `Vehicles/Show.tsx`. Scope: no one-way return-location picker yet (service layer
  supports it since Phase 5; UI doesn't expose it).
- LESSON (real bug found via real HTTP `Location` header): a guest post-checkout
  redirect to a plain `route('bookings.show', $booking)` 403'd for guests — the
  redirect must use the **signed-vs-plain split** (signed for guests) that
  `SendBookingConfirmationEmail` already uses.

## Phase B — the real deposit-gate (verified 2026-08-04)

- **Revises a Phase 5 decision explicitly**: `pending` bookings now block
  availability while their hold is still live (`hold_expires_at` in the future).
  A real hold introduced the first genuine gap in the pending→confirmed
  transition; without blocking, two customers could both pass availability and
  get real holds, with only one reaching `confirmed` (loser stuck with money held
  for a car they can't get). Blocking makes the double-hold race structurally
  impossible — the second checkout is rejected inside `BookingCreator::createPending()`
  before any Stripe call. A null `hold_expires_at` deliberately never blocks (no
  defined "is this hold still live" answer; the pipe never guesses).
- New mechanism: the hold needs a real expiry path or an abandoned checkout locks
  the vehicle forever. `bootstrap/app.php` gained its first `withSchedule()` call,
  running `bookings:release-expired-holds`
  (`Plugins\BookingEngine\Console\Commands\ReleaseExpiredBookingHolds`) every
  minute. This was the project's first scheduler configuration.
- Migration (approved before writing code, rule 7): `bookings.hold_expires_at`
  (nullable timestamp) + composite index on `(status, hold_expires_at)`.
- `BookingCreator` gained `createPending()`/`confirmPending()` alongside unchanged
  `create()` (which still exists for callers that don't need a gate: tests,
  `tinker`, future admin-initiated bookings). `confirmPending()` re-runs the
  availability check (defense-in-depth, rule 9) and is idempotent (safe no-op on
  an already-confirmed booking — guards a retried confirm request double-firing
  the email).
- `PaymentGateway::syncAuthorizationStatus()` (new interface method, implemented
  in `StripeGateway`) closes the timing gap where client-side confirmation
  completes before the async webhook arrives. It shares the exact amount-cross-
  check + target-status logic with the webhook path (`applyIntentState()`), so
  whichever arrives first resolves the row and the second is a safe idempotent
  no-op.
- Built: `store()` calls `createPending()` + `authorizeDeposit()` and renders
  `Bookings/Payment.tsx` (Stripe Elements via `@stripe/react-stripe-js`,
  `redirect: 'if_required'` — stays on-page for the common non-3DS case) with a
  real `client_secret`; a `confirm()` action + `bookings.confirm` route
  synchronously verifies the hold via `syncAuthorizationStatus()` then calls
  `confirmPending()`. If hold authorization fails, the pending booking row is
  deleted (not left orphaned).
- Verified against real Stripe test infrastructure: real PaymentIntent
  (`capture_method: manual`) confirmed with `pm_card_visa` → `requires_capture`;
  `bookings.confirm` → local row `authorized`, booking `confirmed`,
  `hold_expires_at` cleared. Double-hold race proven impossible (second checkout
  rejected, no second Stripe call); expired hold released via the real scheduled
  command, PaymentIntent genuinely `canceled` on Stripe's servers.

## Cancellation refund policy (verified 2026-08-05)

- Pre-flight findings: `ViewBooking.php`'s docblock was stale; `activeAuthorization()`
  (gating Release/Capture) checked only `Payment.type`/`status` with **no
  booking-status check** — since Phase B authorizes a hold before a booking is
  confirmed for pickup, a just-confirmed booking wrongly showed Cancel/Release/
  Capture together.
- Reframing: `chargeFinal()` has zero real callers — the only real money movement
  is the deposit hold. So "refund" is really "how much of a still-held, never-
  captured deposit to release vs. forfeit as a cancellation fee." Mechanism: a
  **partial `captureDeposit()` on a manual-capture PaymentIntent automatically
  releases the uncaptured remainder in the same call** (verified against Stripe's
  docs and a real test-mode call — no new gateway operation needed).
- The visibility-gate fix hit a real conflict: gating Release/Capture to
  `checked_out`/`returned` would have made them permanently invisible (that
  lifecycle still had no dispatch sites). Interim proxy: `pickup_at->isPast()`,
  later retired by the checkout/return lifecycle phase.
- Built: `Plugins\BookingEngine\Support\CancellationPolicyRequest` +
  `CoreCancellationPolicyPipe` on `booking.cancellationPolicy` — cliff/threshold
  refund tiers (`config('booking-engine.cancellation_refund_tiers')`, placeholder
  business numbers). `ViewBooking`'s Cancel Booking resolves the deposit
  automatically (100% → release; less → capture forfeit); the confirmation modal
  shows live computed refund/forfeit amounts before staff confirms.

## Checkout/return lifecycle (verified 2026-08-05)

- `VehicleCheckedOut`/`VehicleReturned` finally get real dispatch sites. Findings:
  `Vehicle.status` has a third real value (`rented`) nothing ever set;
  `CoreAvailabilityCheckPipe` needed zero changes (`BLOCKING_STATUSES =
  ['confirmed','checked_out']` — forward-designed correctly).
- Decisions: **`Vehicle.status` syncs automatically** (Check Out → `rented`,
  Mark Returned → `available`) — the public fleet listing filters purely on this
  field, so manual updates would require a human to remember a second update. The
  "send to `maintenance` if damage found" branch is deferred until damage-
  reporting exists; a clean return always goes to `available`. **No time gate** on
  Check Out/Mark Returned — gated purely on prior status (`confirmed`→checked out,
  `checked_out`→returned), same as every other staff action on the page.
- **Retired the interim proxy**: Release/Capture Deposit visibility now checks
  `status === 'returned'` directly, replacing `pickup_at->isPast()`.

## Vehicle reviews (verified 2026-08-05)

- **`VerifiedRentalChecker` requires a genuine `returned` `Booking`** for that
  vehicle+user — re-derived, not copied from the source's `VerifiedPurchaseChecker`
  (which only needs `payment_status === 'paid'`). Only possible because `returned`
  became reachable in the prior phase. Boundary-tested against all other statuses.
- **`LayoutVariantRegistry`/`LayoutSlot` was documented-but-never-created** — the
  docs asserted kernel infrastructure existed that was never written (6th instance
  of the dormant-bug class, but in the docs themselves). Corrected in
  `docs/event-registry.md` to "planned, not implemented" and why (this project has
  one real theme; the mechanism would serve a hypothetical need).
- **`Review` is a core model (`App\Models`)**, not plugin-owned — otherwise the
  core `ReviewSubmitted` event would import a plugin class (rule 1). Precedent:
  plugin owns the migration/logic/Filament resource, core owns the model (same as
  `DriverVerification`, `DamageReport`).
- **`vehicle.detailWidgets` is the first Slot registered into a plugin-owned page**
  (`fleet-management`'s `Vehicles/Show.tsx`) rather than a core one — proves the
  slot mechanism works when the host page belongs to another plugin (the host
  references only the named slot, never the plugin).

## Kernel fix: ViewBooking importing a plugin directly (verified 2026-08-05)

- `app/Filament/Resources/Bookings/Pages/ViewBooking.php` had
  `use Plugins\BookingEngine\Support\CancellationPolicyRequest;` — a real, live
  Hard Rule 1 violation that slipped through for a full phase.
- Fixed the same way `DriverEligibilityCheckRequest` was placed in Phase 9:
  `CancellationPolicyRequest` is consumed by both a core class and a plugin pipe,
  so it moved to `App\Core\Support` (no behavior change; updated all three real
  consumers). Swept `/app`: `grep -rln "use Plugins\\\\" app/` returns nothing
  else.

## Damage/condition reporting (verified 2026-08-05)

- Scope forks resolved toward the smaller option: free-text description + photos
  (matching `DamageReported`'s shape) over a structured checklist; optional
  "Report Condition" follow-up over making condition-logging mandatory before
  Check Out/Mark Returned (which would have changed actions verified one phase
  ago).
- `App\Models\DamageReport` is a core model; the `damage-reporting` plugin owns
  only the migration (`DamageReportingServiceProvider` is minimal — just
  `loadMigrationsFrom()`). The "Report Condition" action lives entirely on core's
  `ViewBooking` using core classes. `DamageReported` gets its first real dispatch
  site with no listener (deliberate, documented).
- LESSON (Filament v4): `formatStateUsing()` silently skips a state it considers
  "empty" — a zero-photo report rendered blank instead of "0 attached". Use
  `getStateUsing()` to fully override state resolution.

## Analytics dashboard (verified 2026-08-05)

- `AdminPanelProvider` had `->discoverWidgets(...)` configured-but-empty since
  Phase 4 (Filament's own scaffolding, no consumer).
- **"Revenue" would have been substantively false**: `chargeFinal()` (the only
  place a total is ever charged) has zero real callers; the only real money
  movement is the deposit hold. Labeled the metric **"Total Booking Value"**.
- Skipped the source project's custom widget-builder system (`DashboardWidgetRegistry`,
  persisted instances, builder UI) — load-bearing *there* (multiple plugins
  compete for dashboard space), not needed here; use Filament's built-in widget
  classes. Same "don't build a second extensibility layer for a hypothetical
  need" reasoning as `LayoutVariantRegistry`.
- Deliberately different status filters (named, not a silent inconsistency):
  `BookingStatsOverview` counts `confirmed`/`checked_out`/`returned`;
  `BookingVolumeChart` also counts `cancelled` (different question — bookings
  actually received). Both exclude `pending`/`expired` (never completed checkout).
- LESSON: exact-number tests catch real bugs — the utilization window was silently
  30.5 days because `windowEnd` (`now()`) wasn't snapped to day while `windowStart`
  was. Fix: compute both ends from the same instant, no day-snapping on either.
- Rule 8 proven with a query count: 10 vehicles with bookings → exactly 2 queries.
- TEST BOUNDARY: Filament widgets default `$isLazy = true` — plain `curl` or
  `Livewire::test()` against the parent Dashboard never shows real numbers. Test
  each widget's own Livewire component directly.

## Dev database: SQLite → Postgres, and closing the Phase 5 concurrency gap (verified 2026-08-05)

- Dev `.env` now points at a real local Postgres (`car_rental_dev`/`car_rental_test`,
  owned by a `car_rental` user, isolated from the e-commerce `store` DB). `phpunit.xml`
  stays SQLite `:memory:` as the permanent day-to-day test default (only
  *temporarily* pointed at Postgres once to run the concurrency proof + full suite,
  then reverted).
- Real SQLite-vs-Postgres behavioral difference: Postgres aborts the entire
  transaction block on any failed statement (SQLSTATE 25P02) until `ROLLBACK`;
  SQLite doesn't. `ReviewController::store()` now **pre-checks for an existing
  review before inserting** (keeping the try/catch only as a defensive fallback) —
  a strictly better pattern regardless of engine, not a Postgres-specific patch.
- **Closed the Phase 5 concurrency gap** with the strongest proof in the project:
  a genuine two-process test — one process takes `Vehicle::lockForUpdate()` and
  sleeps 3s; a separate process (own DB connection) starts ~21ms later; the second
  process **genuinely blocked 2.987s**, then saw the just-created booking as an
  overlap and rejected it. Real row-level blocking across two simultaneous
  connections, measured.

## Loyalty/repeat-customer discounts (verified 2026-08-05)

- Resolved ledger-vs-tiered toward the smaller option: a tiered discount reusing
  the proven `booking.priceCalculation` pipe pattern (a points ledger would be
  real new infrastructure — same "don't build a second mechanism for a
  hypothetical need" reasoning as extras/CMI/widget-registry).
- Decisions: guest bookings exempt (no persistent identity); only prior `returned`
  bookings count toward a tier (same reasoning as `VerifiedRentalChecker`).
- **Highest-single-discount-wins, not additive stacking** — guarantees the max
  discount on any booking is exactly one tier that was actually defined and
  reasoned about (additive could reach an uncapped number nobody decided on, e.g.
  25% + 15% = 40%).
- `CoreLoyaltyDiscountPipe` registered on `booking.priceCalculation` at priority 15
  (between duration's 10 and deposit's 20 — needs `dailyRate`/`days` set, and may
  replace the discount the deposit pipe must see in the subtotal).
  `PriceCalculationRequest` gained an optional `userId` (threaded from
  `BookingCreator::validateAndPrice()` and the checkout price preview). Placeholder
  tiers (`3 rentals → 5%`, `10 rentals → 15%`).
- Note: `RegistryFlushTest` hardcodes the expected `booking.priceCalculation` pipe
  count — update it when pipes are added/removed.

## Frontend Foundation Task 3 — admin-driven theme system (verified 2026-08-05)

- Ported the centralized admin-driven theme layer on top of Phase 3's file-based
  one (mechanism in `docs/event-registry.md`'s "Theme System" section). Domain-
  agnostic copy. `ThemeResource` rebuilt for Filament v4 (`Schema`/`Schemas\ThemeForm`/
  `Tables\ThemesTable` split), not copied from the v3 source.
- Fonts (Task 4b): **Space Grotesk (display) / Inter (body) / JetBrains Mono
  (mono)**. Only the seeded "Default" theme's font tokens changed; the disposable
  "Demo Rentals" theme untouched (still Poppins). The `primitives.ts` key is `mono`
  (not `jetbrainsMono`) — added `spaceGrotesk` alongside `poppins`/`inter`/`mono`.
- Migration (rule 7): `themes` table (`name`, `slug` unique, `data` json,
  `is_active` bool); `database/seeders/ThemeSeeder.php` ran immediately after the
  migration, before `HandleInertiaRequests` was wired to depend on the table
  having rows.
- Regression: `HandleInertiaRequests::share()` now queries the `themes` table
  every request — the untouched Breeze `ExampleTest.php` (no `RefreshDatabase`)
  broke. Fix the test (add `RefreshDatabase`), not the middleware —
  `ThemeManager::resolveActive()` already degrades to `defaultData()` against an
  empty table.
- Zero-rebuild verified with real Playwright screenshots: uploaded a theme JSON
  through the real `FileUpload` (async upload still mid-progress when Create
  clicked — completed, no lost data), activated it (real `ContrastChecker` ran),
  and the same `GET /vehicles` rendered the new palette **with no `npm run build`
  in between**.

## Frontend Foundation Task 4 — real storefront navigation + homepage (verified 2026-08-05)

- Closes the reachability audit: no shared header/nav/footer, fleet unreachable
  from navigation, `/` was Laravel's Welcome scaffold.
- **Stitch used properly**: downloaded the real HTML export (not just canvas
  screenshots); confirmed a car-rental-specific Stitch project exists
  ("Premium Mobility Design System") with a homepage + fleet screens; header/footer
  live embedded in full-page screens (no standalone screens); **no Stitch screen
  exists for booking confirmation or driver verification** — those were designed
  fresh from the token system.
- `PublicLayout.tsx` — one fixed layout (sticky blurred header, dark multi-column
  footer) following the real Stitch HTML; every color/spacing value via theme
  tokens; every nav item links to a real page (Stitch's marketing nav items not
  copied — no such pages exist). Wired onto all six storefront pages:
  `Vehicles/Index`, `Vehicles/Show`, `Bookings/Checkout`, `Bookings/Payment`,
  `Bookings/Show`, `DriverVerification/Show`.
- Header's conditional "Driver Verification" link: `HandleInertiaRequests` shares
  `driverVerificationStatus` (`'none'|'pending'|'approved'|'rejected'|null`) from
  the user's latest `DriverVerification` row (`DriverVerification` is a core
  model, Phase 9 precedent). Also added a direct link on `Bookings/Checkout`'s
  error state.
- Regression (real production risk, fixed correctly): core middleware
  unconditionally querying the plugin-owned `driver_verifications` table broke 21
  tests — and would 500 every authenticated page if the plugin were disabled. Fix:
  guard with `Schema::hasTable('driver_verifications')` in the middleware
  (degrade to `null`), not by adding `RefreshDatabase` to 21 test files. Same
  "core must not hard-crash over one optional feature" lesson as `StripeGateway`'s
  lazy client.
- Homepage: `Home.tsx` (hero + "Featured vehicles" grid — 4 most recent available
  vehicles, one query, rule 8), scoped to real data (no invented Services/
  testimonials). `Welcome.tsx` deleted outright (confirmed via grep nothing else
  referenced it). `/theme-test`/`ThemeTest.tsx` and the swap-proof theme stay —
  they still serve a proof purpose.

## Frontend Improvement Phase — admin controls + layout variants + server-side filtering (verified 2026-08-07)

Established reusable frontend/admin patterns in one sweep. Hard Rules 10 and 11
were added this session; every pattern below was proven browser-verified with
zero console errors, and admin→frontend round-trips per rule 11.

### Layout variant system
- `App\Core\Support\LayoutVariantRegistry` — the deferred registry is now real,
  API: `register()` / `availableFor()` / `activeComponentFor()` /
  `allRegisteredSlots()`. `availableFor()`/`activeComponentFor()` resolve the
  active variant per region (default fallback when no admin selection);
  `allRegisteredSlots()` lets a host page enumerate every slot registered into it.
- Persistence: `LayoutSetting` model over `layout_settings` table — one row per
  region (`region` + `active_variant`), editable from a `LayoutSettings` Filament
  page. Frontend: `resources/js/layoutComponentRegistry.tsx` maps variant names to
  React components; the `LayoutSlot` component (`registerLayoutSlot(name)`) is the
  per-region render point — a host calls `registerLayoutSlot('vehicleCard')` once,
  loops `allRegisteredSlots()`, renders the active component for each.
- Five regions registered: `vehicleCard`, `fleetLayout`, `reviewDisplay`,
  `checkoutStyle`, `vehicle-gallery` — each with a default variant plus at least
  one alternative. The earlier "don't build a second extensibility layer" call was
  the correct deferral *then*; the mechanism is justified once real regions with
  real alternatives exist.

### VehicleFilterRegistry + VehicleSortRegistry
- `VehicleFilterRegistry`/`VehicleSortRegistry` (`app/Core/Support/`) — server-side
  filtering/sorting backbone for the fleet listing, with contracts
  `VehicleFilterProvider` (label + options + `apply($query, $value)`) and
  `VehicleSortOption` (label + `apply($query, $direction)`). Both flushed at the
  top of `PluginManager::boot()` (same static-state-reset discipline as the
  registry kernel fix).
- Core registers default filters (Category, Transmission) and sorts (`price_asc`,
  `price_desc`, `name_asc`); plugins add their own providers the same way.
  `VehicleController::index()` reads `filter`/`sort` query params, asks the
  registries which are valid, applies them to the `Vehicle` query in one pass, and
  shares applied + available state to the Inertia page. Server-side — the fleet URL
  stays shareable/refresherable.

### VehicleResourceExtension
- `App\Core\Support\VehicleResourceExtension` — plugin relation-manager hook on the
  Filament `VehicleResource` (ported from e-commerce's `ProductResourceExtension`).
  A plugin registers a closure from its own `ServiceProvider::boot()` that receives
  the resource class and calls `pushRelationManagers([...])` — core never
  references the plugin; the plugin calls into the core hook. `vehicle-media` uses
  it to register `VehicleImagesRelationManager` onto the vehicle admin page.

### HomepageContent
- `App\Models\HomepageContent` — singleton-content model (single-row table) with an
  admin `HomepageContentSettings` Filament page for hero/headline/copy + featured-
  vehicle selection. Row resolved and passed to `Home.tsx` as Inertia props with
  fallback defaults (so `/` renders before any save). Same singleton + fallback
  shape as `SiteIdentitySettings`.

### Booking tracking
- `booking_number` auto-generated in the `Booking` model's `creating` hook — a
  human-friendly public reference distinct from the internal id. Public
  `/bookings/track` lookup page (customer enters number + contact detail); the POST
  is throttled to prevent enumeration. Guest result redirect is a signed URL — the
  same signed-vs-plain split used by the checkout redirect and confirmation email.

### SiteIdentitySettings
- Singleton admin settings page (site name, logo, favicon) backed by a single-row
  settings model. Values shared to every Inertia page as a `siteIdentity` prop;
  `SiteLogo` React component renders the configured logo (falling back to site-name
  text) in `PublicLayout` header/footer. Rule 11 applies directly — saving identity
  must change what the storefront header renders (verified as a real round-trip).

### PluginResource
- Admin control making plugin enable/disable manageable from the panel via a
  Filament `ToggleColumn` bound to `is_active`. Combined with
  `PluginManager::boot()`'s per-request flush + re-registration, toggling a row
  genuinely re-runs registration next request — the Phase 4 disable→404/enable→200
  toggle, now a real UI. Rule 11's round-trip is the verification standard.

### Admin panel navigation
- Filament v4 pages customize navigation via getter methods (`getNavigationLabel()`,
  etc.) rather than typed static properties, because the parent `Page` class
  declares those properties with union types that don't narrow to the string
  literals these pages need — the getter form is the v4-correct way.

## Feature Expansion Phase — Scout search, i18n, bulk import, one-way rental, booking export + calendar (verified 2026-08-07)

### Laravel Scout search (storefront autocomplete)
- `App\Http\Controllers\SearchController::suggestions()` powers the SearchBox
  dropdown. `Vehicle` uses Scout's **`database` driver** (`Searchable` trait,
  `toSearchableArray()` limited to public catalog fields — id/make/model/category/
  year — plus a `->where('status', 'available')` guard so a suggestion always
  lands on a 200 page, never a 404 detail page). `/search/suggestions` is throttled
  (`30,1`), returns ≤5 vehicles as a plain JSON array, and batch-loads the primary
  image in one query only when `vehicle-media` has registered the dynamic
  `primaryImage` relation (rule 8; core checks the relation-resolver registry,
  never the plugin's namespace).
- **No search provider credentials in `.env`** — the `database` driver is a
  deliberate local-first default (no Algolia/Meilisearch secret to leak, fully
  offline-capable).
- The suggestions endpoint is a **genuine data-exposure boundary**: checked to
  return only public vehicle data (never booking/customer data); frontend renders
  escaped React text inside a proper ARIA `listbox`/`option`, so an XSS-shaped
  query renders harmlessly (audit probe returned `[]`, zero console errors).

### FR/EN i18n
- Client-side, framework-light: `lang/en.json` + `lang/fr.json` (keys are
  canonical English UI strings; `en.json` is the identity mapping, `fr.json` holds
  French), a `useTranslation()` hook (`resources/js/hooks/useTranslation.ts`)
  reading the locale from Inertia props, and a locale switcher in `PublicLayout`
  navigating to `?lang=en|fr` while preserving existing query params.
- **The `?lang=` param is strictly whitelisted** in `HandleInertiaRequests::share()`
  (`in_array($locale, ['en','fr'], true)`, else `fr`) — a hand-typed
  `?lang=../../etc/passwd` degrades cleanly (no LFI vector). **Admin panel stays
  English-only** (storefront `setLocale()` scoped to storefront requests).
  Default storefront locale is French (`'fr'`).

### Bulk vehicle CSV import
- Admin-only bulk import via **Maatwebsite Excel**: `App\Imports\VehiclesImport`
  owns the CSV column contract + per-row validation (invalid rows skipped with
  per-row reasons); `App\Filament\Pages\BulkVehicleImport` is the shell — a pure
  Blade view (not a Filament form) with a three-step flow: upload → live preview of
  the first 5 parsed rows → Import → inline success/failure summary + Filament
  notification.
- Security detail: the **template download is gated inside the controller, not by
  the route** — `GET /admin/vehicle-import-template` lives in `routes/web.php` with
  only `['web','auth']` (it must be reachable before Filament boot and isn't a
  panel route), and `BulkVehicleImport::downloadTemplate()` does the real
  `abort_unless(hasAtLeast(Role::Admin))`. A deliberate second layer (a Filament
  Page can't easily be referenced in route middleware, and the check is visible at
  the top of the method it protects). Upload validated (`mimes:csv,txt`,
  `max:2048`) and parsed via `toArray(...)` so a malformed file surfaces as a
  caught preview error.

### One-way rental UI
- `Bookings/CheckoutForm.tsx` exposes **distinct pickup/return location selectors**.
  The service layer has supported one-way since Phase 5 — `BookingCreator` accepts
  separate `pickup_location_id`/`return_location_id`.
  `BookingCheckoutController::store()` validates both against the `locations`
  table (`exists:locations,id`) and defaults each to the vehicle's current location
  when absent (a bogus id can never be persisted as a broken FK).
  `RelocateVehicleOnReturn` (Phase 5) already relocates on `VehicleReturned`.

### Booking CSV export
- `App\Http\Controllers\Admin\BookingExportController` streams the currently-
  filtered Bookings list as CSV. Registered as a **Filament panel authenticated
  route** (`AdminPanelProvider::authenticatedRoutes()` → `GET /admin/bookings/export`)
  so it inherits panel auth and is never reachable by storefront users (audit
  confirmed a logged-in customer is redirected away, a non-admin can't call it).
  Mirrors the Bookings table's status SelectFilter + global search; all filters are
  parameterized query-builder `where`/`like` bindings — no SQL injection (the
  audit's `' OR 1=1--` probes returned 200 with unchanged data).

### Booking calendar widget
- `App\Filament\Widgets\BookingCalendarWidget` — deliberately NOT FullCalendar: a
  single self-contained Blade view (`resources/views/filament/widgets/booking-calendar.blade.php`)
  with pickup days (green), return days (red), active rental period (blue) as
  colored dots, hover popovers, prev/next/current month nav. Month navigation is
  plain Livewire state (`$month`/`$year`) — no query strings or extra routes. Status
  filter matches the established "real booking" definition (`confirmed`/
  `checked_out`/`returned`; `pending` holds and `cancelled`/`expired` excluded,
  named explicitly). Rule 8: all overlapping bookings for the month load in one
  query (vehicles eager-loaded in a second); per-day classification in PHP.
  `isLazy()` is `false` — the calendar is the point of the widget.

### Plugin scaffolding command — NOT PRESENT (documented-as-planned only)
- The expected `make:carrental-plugin` Artisan command **does not exist** — verified
  during the security audit (file search, `php artisan list`, composer.json). The
  `add-plugin` skill documents the manual package-scaffolding process, but no
  generator was ever written. Recorded in the same "documented, not implemented"
  category as `LayoutVariantRegistry` was, rather than falsely claimed.

### Layout props contracts
- `resources/js/layout-contracts/` holds shared, server-agnostic TS prop shapes for
  layout-variant components (`VehicleCardProps.ts`, `VehicleGalleryProps.ts`,
  `ReviewDisplayProps.ts`); `pluginComponentRegistry.tsx`'s `SlotOutlet` is the
  generic `LayoutSlot` renderer that merges server props with client-only
  `extraProps` (static PHP props win on collision). The contracts dir lets both
  `fleet-management`'s host page and plugin-owned variant components type props in
  one place without importing each other (rule 2 — the shared type is the
  boundary, same shape as core-owned DTOs for cross-plugin data).

## Resilience patterns (verified 2026-08-08)

Established, cross-cutting production patterns — every feature that talks to an
external dependency must follow these. They are standards, not per-phase
features:

1. **External dependency fallback** — Every external service (Meilisearch,
   Stripe, email) must have a graceful fallback. Use try/catch with a local
   alternative. Proven by `SearchController::suggestions` (Meilisearch-first,
   raw database `LIKE` fallback) and the Stripe checkout flow (failed hold is
   caught and surfaced as a booking failure, never a crash).
2. **Health check** — Every project must have a `/health` endpoint returning
   JSON with DB, cache, search, storage status. This project's `/health`
   (`routes/web.php`) reports `database`/`meilisearch`/`stripe`/`storage`,
   always returns 200 with a `healthy`/`degraded` status + per-dependency
   checks. Note: **cache is not yet in the `/health` payload** — add it when a
   real cache driver (Redis) is used by application code.
3. **Security headers** — CSP, nosniff, DENY, XSS, Referrer-Policy must be
   present on every response. `SecurityHeaders` middleware
   (`app/Http/Middleware/SecurityHeaders.php`) sets all of these for every
   web response, including the CSP (self + Stripe + fonts).
4. **Request timeouts** — External HTTP calls must have timeouts ≤5 seconds.
   Meilisearch: `scout.meilisearch.timeout = 5`. **Wiring note:** Scout's own
   service provider never reads that key — it only passes host/key/clientAgents
   to `Meilisearch\Client`, so the timeout is applied by re-binding the client
   in `AppServiceProvider::boot()` with a PSR-18 Guzzle client
   (`['timeout' => 5]`). Default Guzzle behavior is NO timeout; without this
   rebind a hung Meilisearch would block the request indefinitely. Stripe
   calls go through the SDK's own default retry/timeout behavior — revisit if
   a hang is ever observed.
5. **Correlation IDs** — Every request gets a unique ID returned in response
   headers for debugging. `CorrelationId` middleware
   (`app/Http/Middleware/CorrelationId.php`, web group after SecurityHeaders)
   echoes a client-supplied `X-Correlation-Id` or generates a UUID, and sets
   it on the response — one value to grep logs by per user action.
6. **Circuit breaker** — For external APIs with intermittent failures
   (Stripe, email, future SMS). **Not yet implemented** — see
   `docs/19-CIRCUIT-BREAKER.md` for the agreed shape and the trigger
   conditions to add it (Stripe usage scales, or a second gateway arrives).
   Meilisearch is deliberately excluded — its instant DB fallback makes a
   breaker pointless there.

## Production Readiness Phase — security, CI/CD, accessibility, Meilisearch (verified 2026-08-08)

### Security hardening
- **SecurityHeaders middleware** (`app/Http/Middleware/SecurityHeaders.php`):
  `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection`,
  `Referrer-Policy`, `Permissions-Policy`, HSTS in production only.
- **Rate limiting** on public POSTs: `/vehicles/{id}/book` → `throttle:10,1`
  (prevents hold-flood DoS); `/vehicles/{id}/reviews` → `20,1`;
  `/account/driver-verification` → `20,1`; `/bookings/track` → `20,1`; `/login` →
  5-attempt lockout (Breeze default).
- **CSRF**: `GET /bookings/{id}/confirm` changed to POST; a non-mutating GET
  interstitial handles the Stripe 3DS redirect-back case.

### Meilisearch search
- Meilisearch v1.10 in Docker on `:7700` with `MEILI_MASTER_KEY`;
  `SCOUT_DRIVER=meilisearch`, `meilisearch/meilisearch-php` installed.
  `toSearchableArray()` returns `id, make, model, category, year`; the suggestions
  endpoint queries with `->where('status', 'available')` (**`status` must be
  filterable when switching from the database driver**). Migration guide in
  `docs/17-SEARCH-SUGGESTIONS.md`.

### CI/CD (GitHub Actions)
- `.github/workflows/test.yml`: PHP 8.4 + Postgres 16 + Node 20; composer install,
  npm ci, npm build, Pint, Larastan, TSC strict, php artisan test. Triggers on
  push to `master`/`main`/`feat/*` and PRs to `master`/`main`.

### Production infrastructure
- **Docker Compose** (`docker-compose.yml`): app (PHP 8.4), postgres:16, redis:7,
  meilisearch:v1.10 — one-command dev setup (`docker compose up`).
- **Error pages**: Inertia-rendered 404 (`Errors/NotFound.tsx`) and 500
  (`Errors/ServerError.tsx`), wired in `bootstrap/app.php` via
  `$exceptions->respond()`. PublicLayout is auth-safe (destructures `auth` with a
  default) for unmatched-route 404s where `HandleInertiaRequests` never runs.
- **SEO**: OG meta tags + Twitter card in `app.blade.php`, per-page override via a
  `seo` Inertia prop; vehicle detail sets dynamic og:title.
- **Sitemap**: `GET /sitemap.xml` returns homepage + fleet + all available vehicles.
- **Cookie consent**: `CookieBanner.tsx` — fixed-bottom, localStorage persistence,
  FR/EN translations.
- **Image optimization**: `docs/18-IMAGE-OPTIMIZATION.md` documents the strategy
  (upload-time responsive sizes + WebP via intervention/image, delivery via
  `<picture>` + srcset, CDN caching); gallery images use `loading="lazy"`.

### Accessibility (WCAG 2.1)
- Skip-to-content link in PublicLayout + CheckoutLayout
- Color contrast: warning `#B45309` (4.78:1), success `#15803D` (4.77:1)
- Visible focus indicators on all interactive elements (`focus:ring-focusRing`)
- ARIA: `aria-required`, `aria-describedby`, `aria-invalid`, `aria-label`
- Proper heading hierarchy (h1→h2→h3), configurable `headingLevel` on cards
- Alt text on vehicle images, `aria-hidden` on decorative icons

## Production deployment checklist

Before deploying to production, these settings MUST be changed:

1. **`.env`**: `APP_DEBUG=false`, `APP_ENV=production`
2. **`.env`**: Generate a new `APP_KEY` (`php artisan key:generate`)
3. **`.env`**: Use real Stripe live keys (`sk_live_`/`pk_live_`)
4. **`.env`**: Use real Mailtrap/SES/Mailgun credentials
5. **`.env`**: Set `SCOUT_DRIVER=meilisearch` (with real Meilisearch server)
6. **Server**: Enable HTTPS (TLS 1.3), redirect HTTP → HTTPS
7. **Server**: Set `memory_limit=256M`, `max_execution_time=60`
8. **Server**: Configure queue worker (`php artisan queue:work --daemon`)
9. **Server**: Configure scheduler (`* * * * * php artisan schedule:run`)
10. **Database**: Enable automated backups (daily minimum)
11. **Stripe**: Configure production webhook endpoint + signing secret
12. **CDN**: Serve `/storage` assets from CDN, not the app server
