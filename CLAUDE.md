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

`FilterRegistry`, `SlotRegistry`, `PluginManager` (`app/Core/Support/`),
the `Role` enum (`app/Enums/Role.php`), and the `Plugin` model were ported
unchanged from the prior e-commerce build (`/home/adil/Site-ecommerce`) —
they're fully domain-agnostic. `PluginManager::boot()` is wired into
`AppServiceProvider::boot()`. `config/plugins.php` starts with an empty
`registry` array — the first plugin fills it in (Phase 4, fleet-management).

**`HasMinimumRole` was deliberately NOT copied yet.** It's a trait for
gating Filament Resources/Pages by role, and this project has no Filament
panel or Resources yet — copying it now would leave dead code that
Larastan correctly flags as an unanalyzed unused trait. Copy it back in
from the same source path alongside the first Filament Resource that
actually needs role-gating (expected: the fleet-management admin screen in
a later phase). This is `PROCESS-GUIDE.md` rule 6 in the other direction
from the Phase 1 one-way-rental call: there, front-loading was correct
because the retrofit was expensive; here, deferring is correct because
adding the file back is trivial.

Core domain Events (`BookingRequested`, `BookingConfirmed`,
`BookingCancelled`, `VehicleCheckedOut`, `VehicleReturned`,
`DamageReported`, `DriverVerified`, `PaymentCaptured`) live in
`app/Core/Events/` and are documented in `docs/event-registry.md` along
with the (not-yet-registered) named filters and slots future plugins will
use.

## Phase 3 — Theme engine (verified 2026-08-03)

Three-tier token system ported unchanged from the prior e-commerce build
(`primitives.ts` → `semantic.ts` → `components.ts` → `tokens.ts`), with one
domain adaptation: `components.productCard` renamed to `components.vehicleCard`.
`ThemeProvider` wraps the Inertia `<App>` in `app.tsx`, fed by
`resources/theme/active.ts`, which picks a client theme based on
`window.__THEME__` (injected by `app.blade.php` from `config('site.active_theme')`,
itself from the `ACTIVE_THEME` env var) — swapping is a zero-rebuild runtime
operation, verified by changing `.env` alone and confirming the same JS
bundle hash served two different themes.

**`resources/theme/clients/client-swap-proof-DISPOSABLE.ts` is not a real
client.** It exists only as a second data point to prove the swap mechanism
actually produces different output — delete it (and its `active.ts` entry)
once a real second client theme is needed. Don't mistake "Demo Rentals" for
onboarded client data — this is the same category of thing as the
e-commerce project's disposable test categories, flagged explicitly up
front this time instead of needing a later cleanup note.

**Found and fixed during this phase:** `tailwind.config.js`'s content globs
were a Phase 1 leftover — still `*.jsx` after the TypeScript conversion
renamed everything to `.tsx`, silently purging almost every utility class
actually used in the app (confirmed via a missing `text-red-600` in the
compiled CSS). Fixed to `*.{ts,tsx}` across `resources/js` and
`plugins/**/resources/js`. Watch for this same class of bug (build-tool
glob patterns silently drifting from actual file extensions) after any
future bulk rename/conversion.

A throwaway `/theme-test` route + `Pages/ThemeTest.tsx` page exists purely
to prove token-driven Tailwind classes (`bg-primary`, `rounded-interactive`,
etc.) render correctly — remove once a real themed page (fleet listing,
Phase 4) makes it redundant.

## Phase 4 — First plugin: fleet/vehicle catalog (verified 2026-08-03)

Proves the full plugin pattern end to end for the first time: `Vehicle` is a
core model (per the Phase 1 decision), but the public storefront experience
(listing + detail pages) is a real Composer plugin at
`plugins/fleet-management/`, wired via a `path` repository in the root
`composer.json` and `config/plugins.php`'s `registry` array — same split as
the source project's `catalog` plugin (core owns the model, the plugin owns
the storefront controller/routes/pages).

**Environment reality: Filament v3 does not support Laravel 13.** The
source project's `composer.lock` had `filament/filament v3.3.54` alongside
`laravel/framework v13.20.0`, but a fresh `composer require filament/filament:^3.2`
fails to resolve against this project's Laravel 13 — v3's `illuminate/auth`
constraint tops out at `^12.0`. Installed **Filament v4** instead, which
restructured its API significantly (`Filament\Schemas\Schema` replaces
`Filament\Forms\Form`; resources now live under
`App\Filament\Resources\Vehicles\` with separate `Schemas/VehicleForm.php`
and `Tables/VehiclesTable.php` classes, not one flat resource file). Do not
copy Filament resource code from the source project verbatim — the shape
changed. Use `php artisan make:filament-resource` to generate a v4-correct
stub, then port field-by-field.

`HasMinimumRole` came back out of its Phase 2 deferral exactly as planned —
`VehicleResource` is the first real consumer, and Larastan confirms the
trait is no longer flagged as dead code.

**A real Laravel 13 → Filament v4 gap found and closed:** Filament v4's own
generated `EnsureAdminPanelAccess`-equivalent middleware pattern from the
source project (`extends Filament\Http\Middleware\Authenticate`, overriding
the `protected authenticate()` method to redirect instead of abort) doesn't
translate cleanly to v4's `authenticate()` signature/flow. Rewrote
`EnsureAdminPanelAccess` as a full `handle()` implementation instead of
extending the base middleware.

**Genuinely toggleable, proven three ways, not just described:** with
`fleet-management` disabled in the `plugins` table, `/vehicles` → 404. After
`PluginManager::activate('fleet-management')` and a fresh request, `/vehicles`
→ 200 with real DB-backed vehicle data (available vehicles only; a
`maintenance`-status vehicle correctly 404s on its detail page). After
`PluginManager::deactivate()`, `/vehicles` → 404 again. Each check used a
genuinely fresh `php artisan serve` process boot against the persistent dev
DB — not the same PHP process across checks.

**Found a real automated-testing limitation, documented rather than
faked:** PHPUnit's `TestCase::setUp()` boots the app (and therefore
`PluginManager::boot()`) *before* `RefreshDatabase` migrates the in-memory
test DB, so the `plugins` table never exists at boot time during any test
run — the provider is never auto-registered inside PHPUnit regardless of
`activate()`/`deactivate()` calls in a test body. The disable/enable/disable
toggle above can only be verified via real process-per-request testing (as
done above), not inside the automated suite. The automated
`VehicleControllerTest` instead registers the plugin's ServiceProvider
directly in `setUp()` to cover the actual business logic (available-only
filtering, 404 on non-available), and uses literal paths (`/vehicles`) rather
than the `route()` helper — `route()`/`Route::has()` don't reliably see
routes registered by a provider added after boot in tests, even though real
HTTP dispatch to the same path works correctly (a `UrlGenerator` caching
quirk specific to post-boot route registration in tests, not a bug in the
plugin).

**Repeatable re-verification:** `scripts/verify-plugin-toggle.sh [slug] [path]`
automates the exact disable→404→enable→200→disable→404 sequence above via
real `php artisan serve` process boots, and restores the plugin to enabled
afterward. Run it after any change to `PluginManager`, `AppServiceProvider`,
or a plugin's route registration — e.g. `scripts/verify-plugin-toggle.sh
fleet-management /vehicles`. This exists specifically because PHPUnit can't
cover the real toggle (see above); don't let this become "it worked once
when I checked it live" — re-run the script, don't just trust memory of a
past manual check.

## Phase 5 — Booking/availability engine (verified 2026-08-03)

This is the highest-scrutiny code in the project per rule 9 — a bug here
double-books a real physical car. Three explicit decisions were confirmed
before any code was written (see `docs/event-registry.md`'s
`booking.availabilityCheck` section for the mechanism itself):

1. **Exclusive-end boundary, no turnaround buffer.** A booking ending at
   10:00 and another starting at 10:00 on the same vehicle do NOT overlap.
   A buffer is deliberately not built into the core query — it bolts on as
   a second pipe on `booking.availabilityCheck` later. **This is flagged in
   `docs/03-DOMAIN-REQUIREMENTS.md` as a must-configure-before-real-launch
   item, not a someday-maybe feature** — don't let that distinction get
   lost if this project sits untouched for a while.
2. **Only `confirmed` and `checked_out` bookings block** (`pending` and
   `cancelled` do not), per the domain doc's explicit wording. This means
   two `pending` requests for the same vehicle/dates can coexist — safety
   at confirm time comes from `BookingCreator`'s lock, not from the
   overlap query excluding pending rows.
3. **Strict location matching** — a vehicle is only bookable at its
   current `location_id`, updated automatically by
   `RelocateVehicleOnReturn` when `VehicleReturned` fires (this is how a
   one-way rental's car correctly "belongs" at the drop-off location
   afterward). No staff-override path exists yet — deliberately deferred
   as a separate feature (its own authorization/audit-trail scope), not
   folded into this phase.

**Concurrency — addressed now, not deferred, with an honest limitation
stated:** `BookingCreator` is the only sanctioned way to create a
`confirmed` booking. It wraps the availability recheck + insert in a DB
transaction with `Vehicle::lockForUpdate()`, so two concurrent confirm
attempts for the same vehicle serialize — the second one's recheck
correctly sees the first's committed booking and fails. Verified two ways:
14 automated tests cover every overlap/boundary/status/location case plus
a **sequential** proof that a second `BookingCreator::create()` call fails
after the first succeeds; and real `tinker` verification (not just
`tinker` — full round trip through the actual service) walked through a
successful booking, a rejected overlap, a rejected location-mismatch, a
real one-way relocation via `VehicleReturned`, and confirmed the
relocation's consequence (old location now rejects, new location
accepts).

**What is NOT proven, stated honestly rather than papered over:** this
project's dev/test DB is SQLite, which has no true row-level locking (it
serializes at the whole-database level instead). The lock call is correct
and portable — Laravel abstracts `lockForUpdate()` — but genuine
concurrent-connection race protection can only be meaningfully verified
against a database with real row-level locks (MySQL/Postgres), which
hasn't been chosen for this project yet. If/when a production DB is
chosen, add a real concurrent-connection integration test at that point —
don't treat the sequential test as equivalent proof.

## Phase 6 — Pricing engine (verified 2026-08-03)

Scope drawn deliberately narrow: base daily rate × duration + duration
discount tiers + deposit computation, via `booking.priceCalculation`
(mechanism documented in `docs/event-registry.md`). Two things explicitly
NOT built here, on purpose:
- **Extras** (GPS, child seat, additional driver, insurance tiers) need
  their own data model (add-ons table, booking-extras pivot) — that's a
  real second feature, not a line in this filter. Building it now would be
  inventing structure ahead of a concrete need (rule 6).
- **Actually charging** the deposit or rental total is the separate
  Payment/gateway phase (`PaymentGatewayRegistry`, `PaymentCaptured`) —
  this phase only computes numbers, never moves money.

**Three business-policy decisions confirmed before building** (these are
tuning knobs in `plugins/booking-engine/config/booking-engine.php`, not
foundational schema bets like Phase 5's — cheap to change later without
touching the availability-overlap query):
1. **Cliff/threshold discounts**, not graduated — hit 7 days, the whole
   booking gets 10% off; hit 30, the whole booking gets 25% off. Not
   cumulative across tiers.
2. **Partial days round up** to the next full day, minimum 1 day.
3. **Deposit = a flat percentage of the discounted subtotal**
   (`deposit_percentage_of_subtotal` config, currently 20%) — not
   per-category. Per-category deposits are a filter change away if needed
   later, not a schema change, since deposit computation already lives in
   this same pipeline.

**A Phase 5 correctness gap closed:** `BookingCreator` previously accepted
`total_price` as a raw caller-supplied value (acceptable in Phase 5, which
was explicitly scoped to availability only, before pricing existed). Now
that real pricing logic exists, that was a gap under rule 5's higher bar
for financial code — `BookingCreator` now computes `total_price` and
`security_deposit_amount` internally via `booking.priceCalculation` and
ignores any caller-supplied values for both. Proven, not just asserted: a
test explicitly passes a bogus `total_price` and confirms the persisted
value is the correctly computed one instead.

**Verification matches the Phase 5 standard — exact expected numbers, not
formula descriptions:** 8 automated tests cover both discount tier
boundaries (6/7/29/30 days) with hand-computed exact totals (e.g. 7 days
at 100/day → 630.00, not "some discounted amount"), both partial-day
rounding cases (above and exactly at a day boundary), the deposit
percentage, and a full `BookingCreator` integration proving the computed
values are what actually gets persisted. Real `tinker` verification against
actual fleet data (Vehicle #3, daily_rate 450.00, 10-day booking) matched
the hand-computed expected total (4050.00) and deposit (810.00) exactly.

## Phase 7 — Payments (verified 2026-08-03)

`PaymentGatewayRegistry` and the `PaymentGateway` contract shape live in
`app/Core/{Support,Contracts}`; `payments-stripe` (Phase 7's only gateway
plugin) implements it. Full mechanism documented in
`docs/event-registry.md`'s "Payment Gateways" section — read that first.

**The `PaymentGateway` interface could not be ported verbatim from the
e-commerce project**, and this was verified by actually reading the source
code, not assumed: the e-commerce `StripeGateway` uses Checkout Sessions
(`mode: payment`), which capture immediately — there's no way to represent
"hold now, decide later" with it. `payments-stripe` uses the **PaymentIntents
API with `capture_method: manual`** instead, a materially different Stripe
API surface. The interface itself grew a third operation family (authorize/
capture/release, alongside charge/refund) that the e-commerce project's
one-shot-charge shape had no need for.

**CMI was deliberately not built.** Reading the actual source `CmiGateway`
showed it has no hold/pre-auth concept at all (refund is unimplemented) —
it cannot fulfill this domain's deposit-hold requirement. Combined with the
business being primarily international-customer-facing (confirmed
2026-08-03), Stripe is the priority gateway; CMI is deferred, not a
scope-creep addition.

**A real, serious bug found by actually running the app, not by reading
the code:** `StripeGateway`'s constructor originally built `StripeClient`
eagerly. Since `PaymentGatewayRegistry::register(new StripeGateway, ...)`
runs in `StripeServiceProvider::boot()` — on every request, the instant the
plugin is enabled — an empty `STRIPE_SECRET` took down the **entire site**
(`/`, `/vehicles`, even `artisan tinker`), not just payment pages. Confirmed
broken, then confirmed fixed: `stripe()` now builds the client lazily
(`??=`) on first actual use. Verified by reproducing the failure (enabled
the plugin with empty credentials, watched `/` and `/vehicles` both break),
applying the fix, and confirming the same pages load fine afterward — not
just reasoning that the fix should work.

**Test coverage, and an honest line about what it does and doesn't prove:**
- 6 webhook tests exercise the **real** `handleWebhook()` entry point
  end-to-end, including **real Stripe-Signature HMAC verification** — no
  mocking of the signature check itself, since it's pure local computation
  (Stripe's documented algorithm: sign `"{timestamp}.{payload}"` with
  HMAC-SHA256). Covers idempotency (same event delivered twice is a
  no-op), the amount cross-check (gateway-reported amount must match our
  own computed price), and invalid-signature rejection.
- 6 gateway tests cover the local side effects (`Payment` rows created
  with the right type/status/amount) of `authorizeDeposit`/`captureDeposit`
  (full and partial)/`releaseDeposit`/`chargeFinal`/`refund`, using a
  Mockery double of `StripeClient` so no real network call or API key is
  needed.
- At the time Phase 7 was first built, real Stripe test credentials
  weren't available in this environment — the Mockery doubles verified our
  code calls the SDK correctly and records the right local state, but
  could not catch a wrong assumption about Stripe's actual behavior. Real
  end-to-end verification at that point was done via genuine HTTP requests
  with a manually-computed valid HMAC signature (using `openssl`, not the
  test suite) against a real `Payment` row — confirmed an invalid
  signature gets a real 400 over real HTTP, and a validly-signed request
  correctly transitions a real DB row from `pending` to `authorized`.

**Gap closed 2026-08-03**, once real Stripe test-mode credentials became
available (reused from the e-commerce project's `.env` — genuine
`sk_test_`/`pk_test_` keys, not live ones; Mailtrap sandbox mail
credentials copied the same way): `authorizeDeposit()` and
`releaseDeposit()` were called for real against `api.stripe.com`, not
mocked. A real `PaymentIntent` was created with `capture_method: manual`
— confirmed via a direct `GET` to Stripe's API that the live object has
the exact expected `amount` (90000), `currency` (`mad`), and `metadata`
(`booking_id`, `payment_type`). It was then released, and a second `GET`
confirmed Stripe's own record shows `status: canceled`. This is real proof
the manual-capture design actually works against Stripe's real
infrastructure, not just proof our code calls the SDK the way we think it
does.

**`PaymentCaptured`'s constructor signature changed** from its Phase 1/2
placeholder shape (`Booking $booking, string $type, float $amount`) to
carrying the real `Payment` model — safe, since nothing consumed the old
shape yet. Three more events added: `PaymentAuthorized`, `PaymentFailed`,
`PaymentReleased` (distinct from `PaymentRefunded` — a release cancels a
hold with no money moved, a refund reverses money that was captured).

**Also fixed:** `bootstrap/app.php` was missing the CSRF exclusion for
`webhooks/*` that the source project's own `bootstrap/app.php` has — added
before it could become a silent webhook failure in a later phase.

**Business/legal note, not a code decision:** Stripe requires the
account-holding business to be domiciled somewhere Stripe operates — see
`docs/03-DOMAIN-REQUIREMENTS.md`'s Payment section and the
`stripe_entity_status` memory. Confirmed 2026-08-03 that a foreign entity
is already in motion for this business, so this isn't expected to block
going live — worth a quick re-confirmation at the actual go-live moment
regardless, since this is a fact about the business, not the code, and
business circumstances can change.

## Locations admin CRUD (verified 2026-08-03) — closing a HIGH item 5 gap

After Phase 7, `03-DOMAIN-REQUIREMENTS.md`'s HIGH item 5 (pickup/return
locations, one-way support) looked done — the schema, the availability
enforcement, and the one-way relocation logic (Phase 5) were all real and
tested. **It wasn't actually done.** There was no way for an admin to
create, edit, or list locations at all — every `Location` row in the
project existed only because a test factory or a `tinker` call put it
there. A real business couldn't add a second city without direct DB
access. Caught by actually searching for a `LocationResource`/routes/pages
and finding nothing, not by assuming the schema + logic implied a feature.

Added `App\Filament\Resources\Locations\LocationResource` (same shape as
`VehicleResource`): name, address, city, country, lat/lng, an `is_active`
toggle.

**`Location.is_active` had existed on the schema since Phase 1 but was
never wired into anything** — deactivating a location had zero effect
prior to this. Wired it into `CoreAvailabilityCheckPipe`: an inactive
location now blocks new bookings requesting pickup there. Decision:
`is_active` is a soft-disable for **future** bookings only — deactivating
a location with existing confirmed bookings referencing it is not
blocked, and those bookings remain valid (same precedent as a `Vehicle`'s
`status` field not retroactively touching its existing bookings).

Verified with real data, not just tests: deactivated the real Casablanca
Airport location, confirmed a fresh `BookingCreator::create()` call for a
vehicle homed there was genuinely rejected, reactivated it, confirmed the
identical call then succeeded. 18 new/updated automated tests (Location
CRUD + role-gating, plus the new `is_active` availability case, including
both boundary directions already covered by the exclusive-end tests).

**Unrelated finding, not a bug, worth remembering:** nested
`Model::factory()` references inside a factory's `definition()` (e.g.
`BookingFactory`'s default `pickup_location_id`/`return_location_id` =>
`Location::factory()`) get eagerly evaluated and persisted even when the
caller overrides that field in `create([...])` — a known Laravel factory
behavior, not specific to this codebase. Harmless under `RefreshDatabase`
in the automated suite (wiped every test), but produces orphaned rows if
you call `Model::factory()->create([...])` with overrides directly against
the persistent dev DB via `tinker`, as happened during Phase 7's real
Stripe verification. Clean up orphaned rows after any such manual
verification — don't assume an override on a factory `create()` call
prevented the nested factory row from being created.

## Bookings admin CRUD (verified 2026-08-03) — third instance of the same pattern

Same root cause as `Location.is_active` and the missing `LocationResource`
itself: `Payment::captureDeposit()`/`releaseDeposit()`/`refund()` (built in
Phase 7) had **no real caller anywhere in the application** — only tests
and manual `tinker` verification ever invoked them. Modeled, tested, and
completely unreachable in production. Found by directly checking: this
premise ("is there a BookingResource/payments screen") was raised as
something supposedly already discussed, and there was no record of it
anywhere in the project — worth naming explicitly, since agreeing to a
false premise here would have meant building on top of an assumption
nobody actually verified.

Added `App\Filament\Resources\Bookings\BookingResource` — **deliberately
no create/edit pages**. A `Booking` must only ever be created through
`Plugins\BookingEngine\Support\BookingCreator` (enforces the availability
check, computes price via `booking.priceCalculation`); an admin form that
could set arbitrary dates/prices would silently reopen the exact
raw-caller-supplied-price gap Phase 6 closed. List + View only.

**The View page is where `releaseDeposit()`/`captureDeposit()` finally get
a real caller**: two explicit staff actions, "Release Deposit" (clean
return) and "Capture Deposit" (damage — staff enters an amount, full or
partial), both gated on an active `deposit_authorization` `Payment`
existing. Deliberately manual, not automatic on `VehicleReturned` — same
reasoning as Phase 7's deferred damage-charging workflow: deciding
release-vs-capture requires a human inspecting the vehicle, not a pipeline
step.

Verified three ways: 5 automated tests (visibility gating, and both
actions calling a mocked `PaymentGateway` with the exact expected
arguments); Larastan caught two real magic-property/generics gaps in the
process (`$this->record`'s broad `Model|int|string` type needed a real
`instanceof` narrowing helper, not a suppression — same for
`HasMany::first()`'s type erasure on the payments query); and — the
strongest evidence — the **exact same `releaseDeposit()` call the
"Release Deposit" button invokes** was run for real against
`api.stripe.com` via the actual registered `PaymentGatewayRegistry`
gateway (not a mock), and a follow-up `GET` confirmed Stripe's own record
shows `status: canceled`. That closes the loop from "the button exists" to
"the button's underlying call has been proven against real Stripe
infrastructure."

## Phase 9 — Driver verification (verified 2026-08-04)

**Pre-flight check per `PROCESS-GUIDE.md` rule 13:** searched Phase 1's
schema for any driver-related fields before writing a line of code —
genuinely clean, nothing existed (`Vehicle.license_plate` is the car's
plate, unrelated). No retrofit needed, confirmed rather than assumed.

**Real architectural constraint, not a detail:** `driver-verification` and
`booking-engine` are separate plugins, and `BookingCreator` (booking-engine)
needs to check eligibility, which only `driver-verification` can answer.
Neither plugin may reference the other's classes (Hard Rule 2), so the
eligibility check is a new core-owned filter —
`App\Core\Support\DriverEligibilityCheckRequest` lives in core specifically
so both plugins can depend on it without depending on each other. Full
mechanism in `docs/event-registry.md`.

**Two real business-model forks resolved before building, not defaulted:**
1. **Verification is per-User, not per-Booking.** Per-Booking would work
   for guest bookings too, but requires an async admin-review step to
   complete *before* a booking can be confirmed — a real architectural
   change to `BookingCreator`'s current immediate-confirm design (no
   pending→reviewed→confirmed workflow exists yet). Per-User composes with
   what's already built.
2. **Guest bookings are exempt from verification in this phase**,
   consequence of #1 — enforcing it for guests would mean either forcing
   account creation (ending true guest checkout for restricted categories)
   or building the pending-booking workflow. Deferred explicitly, same
   category as extras/damage-charging in Phases 6–7, not silently decided.

**Age eligibility is evaluated at the booking's `pickup_at` date, not
"today"** — same reasoning as Phase 6's partial-day rounding: a driver who
turns 21 the week after booking but before pickup is correctly eligible.
Tested at the exact boundary (born exactly 21 years before pickup → eligible;
one day short → rejected), not just "old enough"/"too young" cases.

**A genuinely new cross-plugin-Filament-resource pattern, ported from the
source e-commerce project after verifying it, not assumed:** `AdminPanelProvider`
only auto-discovers `app/Filament/Resources`. `driver-verification`'s
`DriverVerificationResource` lives inside the plugin itself and registers
itself into `Filament::getDefaultPanel()` from its own `ServiceProvider::boot()`
— confirmed this exact pattern exists in the source project's `reviews`/
`promotions` plugins before copying it, rather than inventing a mechanism.
Core never references the plugin's namespace.

**A real correction caught before it mattered:** the `driver_verifications`
migration was initially placed in `database/migrations/` (core) — wrong,
since this is genuinely plugin-owned data, not shared/core (rule 6). Moved
into `plugins/driver-verification/database/migrations/` with
`loadMigrationsFrom()` added to the ServiceProvider; confirmed the move was
safe by checking `migrate:status` still showed it as `Ran` (Laravel tracks
migrations by filename, not path).

**A new class of test-environment limitation found, the same root cause
hitting three different tests:** this is the *first* plugin with its own
migrations, and the *first* plugin-owned Filament resource — both expose
the same underlying issue from two new angles. `RefreshDatabase` (via
`parent::setUp()`) migrates only paths known before a test manually
registers a plugin, so plugin migrations must be run explicitly
(`$this->artisan('migrate', ['--path' => ...])`) in every test that needs
them. Separately, the Phase 4-documented `route()`/`UrlGenerator` caching
quirk (routes registered post-boot aren't visible to the helper, even
though real HTTP dispatch works) this time hit **Filament's own internal
rendering** (the List page's row-URL generation, and even its own
breadcrumbs) and **this project's own controller code** (`redirect()->route(...)`)
— not just test assertions. Both are genuine test-harness-only artifacts:
real production registers everything during the app's normal pre-render
boot, so neither issue exists there. Resolved per-case: the List page test
asserts the route is genuinely registered (bypassing `UrlGenerator`
entirely) rather than a full HTTP render; the controller test asserts the
real business-logic side effects (the DB row, the stored file — both
happen *before* the failing `redirect()->route()` call) rather than the
response itself, with the expected exception caught and documented inline.

**Real end-to-end verification, not just tests:** activated the plugin for
real, then walked through the actual lifecycle against real fleet data —
a real user with **no verification** rejected from booking an SUV (min age
21); the same user with a **pending** verification still rejected; approved
via the exact same DB update the admin action performs (plus firing
`DriverVerified`); the same booking attempt then succeeded. All three
states shown with real rejection messages / real booking IDs, not asserted
in the abstract.

## Booking confirmation email (verified 2026-08-04)

**Pre-flight caught a fourth instance of "modeled but never consumed"
(rule 13), and a more serious variant of it.** `App\Core\Events\BookingConfirmed`
has existed since Phase 2 but was never dispatched anywhere — `BookingCreator`
set `status: 'confirmed'` directly and returned. Worse than a missing dispatch
call: `docs/event-registry.md`'s own description of the event ("fires when a
booking is accepted **and the deposit/payment is captured**") described a
two-step request → deposit-hold → confirm flow that was never actually built.
`Payment::authorizeDeposit()` (Phase 7) has zero callers in the booking flow —
confirmed by grep, not assumed. The documentation was describing a system that
doesn't exist, not just missing a call site.

**Resolved by dispatching reality, not by building the missing system.**
`BookingConfirmed::dispatch($booking)` now fires at the end of
`BookingCreator::create()`, exactly where and how the code actually confirms a
booking today — immediately, with no payment gate. `docs/event-registry.md`
was corrected to describe the real one-step flow, with deposit-gated
confirmation named explicitly as a real, undesigned future decision (sync vs.
async Stripe call inside the transaction, what happens to the booking if the
hold fails) rather than left implied as already solved. Same reasoning as
every other scope-boundary call in this project (extras, damage-charging,
CMI): building the gate is its own phase, not a side effect of wiring up an
email.

**Second real fork, resolved narrow:** the source e-commerce project's
`OrderConfirmation` email links to a real `orders.confirmation` page, with a
`URL::temporarySignedRoute` for guests. This project has no public
booking-detail page at all yet. Rather than build one as a side effect of
this phase, the confirmation email is self-contained — vehicle, pickup/return
dates and locations, total price, and deposit amount shown inline, no link.
A public `bookings.show` page (with the same guest-signed-URL split) is
flagged in `docs/03-DOMAIN-REQUIREMENTS.md`'s MEDIUM section as its own
deferred item.

**Mechanism, ported from the source project's `OrderPlaced`/
`SendOrderConfirmationEmail`/`OrderConfirmation` pattern after actually
reading it (not assumed):** `App\Core\Listeners\SendBookingConfirmationEmail`
(a plain core listener, registered via `Event::listen()` in
`AppServiceProvider::boot()` — no dedicated `EventServiceProvider` exists in
this project, matching the existing pattern `RelocateVehicleOnReturn` already
established for plugin-registered listeners) resolves the recipient as
`$booking->guest_email ?? $booking->user?->email` and no-ops if neither
exists. `App\Mail\BookingConfirmation` is a queued `Mailable` rendering
`resources/views/emails/booking-confirmation.blade.php` — plain inline CSS,
not theme tokens, matching the source project's own emails (email clients
can't consume CSS custom properties). Both the listener and the Mailable
implement `ShouldQueue`; no new migration needed, since the `jobs` table and
`QUEUE_CONNECTION=database` were already in place.

**Verified three ways:** 5 automated tests (event dispatch, guest-email
recipient resolution, user-email fallback, no-recipient no-op, Mailable
subject) plus the full 109-test suite, Pint, and Larastan all still passing.
Real `tinker` verification created a genuine booking through
`BookingCreator::create()`, confirmed exactly one job (the listener) was
queued, ran it for real via `php artisan queue:work --once`, and confirmed it
correctly re-queued `App\Mail\BookingConfirmation` (expected — the Mailable
also implements `ShouldQueue`). Processing that second job against the real
Mailtrap sandbox failed with a TLS certificate verification error — traced
to this sandbox's PHP CLI having no `php.ini` loaded at all (`php --ini`
reports "(none)"), so `openssl.cafile` is unset even though the system CA
bundle at `/etc/ssl/certs/ca-certificates.crt` is present and valid (a raw
`openssl s_client -starttls smtp` to Mailtrap from the same shell verifies
cleanly). **This is an environment gap, not a code defect** — confirmed by
re-running the exact same `Mail::to(...)->send(new BookingConfirmation(...))`
call with `-d openssl.cafile=/etc/ssl/certs/ca-certificates.crt` explicitly
set, which succeeded against the real Mailtrap sandbox with no exception.
Test data (the tinker-created booking, vehicle, and location) and the
`failed_jobs` row were cleaned up afterward. If this sandbox's PHP CLI is
still missing a loaded `php.ini` in a later phase, worth fixing at the
environment level rather than re-discovering this each time — not done here
since it's a persistent system change outside this phase's actual scope.

## Kernel fix: `FilterRegistry`/`SlotRegistry` static-state accumulation (verified 2026-08-04)

Found while building the booking-history phase (below), and treated as its
own separate, focused fix rather than folded into that phase — this is a
change to shared kernel infrastructure every `booking.*` filter has
depended on since Phase 5, and per this project's own discipline, kernel
changes get isolated scrutiny.

**A meaningfully different class of bug than every prior "modeled but never
consumed" catch.** Those were all *missing* wiring (an event never
dispatched, a method never called). This was *present* wiring with a latent
correctness bug, silently masked by luck rather than by anything actually
being correct — "all tests pass" gave zero signal anything was wrong.

**Root cause:** `FilterRegistry::$pipes` and `SlotRegistry::$slots` are
`static` arrays, and Laravel's test suite boots a brand-new `Application`
for every single test method within the same PHP process — re-running every
ServiceProvider's `boot()`, including `AppServiceProvider::boot()` (which,
as of the booking-history phase, unconditionally calls
`SlotRegistry::register('account.dashboardWidgets', ...)` on every boot).
Nothing ever cleared the static arrays between boots, so they accumulated
without bound across an entire test run — confirmed empirically with a
throwaway diagnostic test: `FilterRegistry::pipesFor('booking.priceCalculation')`
measured 2 → 4 → 6 entries across three consecutive test methods that each
register `BookingEngineServiceProvider`.

**Why this hadn't broken anything before now:** `CoreDurationDiscountPipe`
and `CoreDepositPipe` (the only `booking.priceCalculation` pipes) both
recompute their result from scratch from the immutable
`$breakdown->request` every time, rather than compounding on the previous
pass's output — so running the same pipe 2, 4, or 6 times in a row still
produces the identical final number, and `PriceCalculationTest`'s exact
hand-computed totals kept passing by coincidence.
`booking.availabilityCheck`/`booking.driverEligibilityCheck` are boolean
short-circuit checks, equally immune by accident. This was fragile, not
safe — the first future pipe that isn't purely re-derivative (e.g. a
promo-code pipe decrementing a usage counter) would have been silently
double- or triple-applied with nothing distinguishing "ran once correctly"
from "ran three times and happened to land on the same number." Booking
history's `SlotRegistry` usage has no such luck — a widget genuinely
renders N times — which is what actually exposed this.

**Scope of real-world risk, checked rather than assumed:** this project
does not run Laravel Octane (confirmed via `composer.json`), so in real
deployment each PHP-FPM request is a fresh process and this never
accumulates there today. This was a test-environment-only symptom right
now — but the underlying design gap (registries assuming `boot()` runs
exactly once per process, with no reset path) would resurface immediately,
silently, in production the moment a persistent-worker deployment model is
ever adopted.

**Fix:** `flush()` added to both `FilterRegistry` and `SlotRegistry`
(clears the static array), called at the top of `PluginManager::boot()` —
so every boot cycle starts from a genuinely clean registry state before
re-registering. No public API changed for any existing pipe/slot
registration call site.

**Verified three ways, not just "tests pass":** (1) the same diagnostic
approach that caught the bug, re-run after the fix — pipe/slot counts now
stay at a constant 2/1 across three consecutive test methods, instead of
growing; (2) the fix was temporarily reverted and the new
`RegistryFlushTest` regression test was confirmed to actually fail without
it (2 of 3 methods failed with counts of 4 and 6, matching the diagnostic
exactly) before being restored — proving the regression test is a real
guard, not a tautology; (3) the full 121-test suite, Pint, and Larastan all
still pass after the fix, plus `tsc --noEmit --strict`.

**General instinct worth keeping, not just this one fix:** this is the
second time in this project a mechanism nobody was actively exercising
turned out to be structurally fragile the moment a real first consumer
arrived (`HasMinimumRole` waiting for its trigger was a deliberate
deferral; this was an accidental one — the fragility was already latent,
just unobserved). Any shared static/kernel state deserves an explicit "does
this actually reset correctly across the process lifecycles that matter"
check the first time something real depends on it, not only after a bug
happens to surface it.

## Booking history + the deferred confirmation-page gap (verified 2026-08-04)

Closes the public-booking-page gap deliberately deferred in the
booking-confirmation-email phase, and is the first real consumer of
`SlotRegistry` (see the kernel fix above, found while building this).

**Pre-flight found the actual source pattern was different from what the
domain doc's wording ("reuse RecentOrders-equivalent pattern") implied on
its own.** Reading the real source code (not assuming from the name) showed
`RecentOrders` isn't a dedicated history page/route — it's a Dashboard-slot
widget (`SlotRegistry::register('account.dashboardWidgets', ...)`)
rendered from `ProfileController::edit()` into `Profile/Edit.tsx`, linking
to the *same* `orders.confirmation` page used for the guest email link. Two
follow-on corrections during the same pre-flight, both caught by tracing
the actual mechanism rather than trusting a name already agreed to:
`Dashboard.tsx` was assumed to be the host page in an earlier exchange —
wrong; the source project's own `Dashboard.tsx` is *also* an untouched
Breeze stub, and the real widget host is `Profile/Edit.tsx`. Corrected
before any code was written on top of the wrong assumption, not after.

**Built:** `resources/js/pluginComponentRegistry.tsx` (the `SlotOutlet`
mechanism — didn't exist in this project before now, ported from source),
`Widgets/BookingHistory.tsx` (the widget), `SlotRegistry::register()` call
in `AppServiceProvider::boot()`, `ProfileController::edit()` now batch-loads
the user's last 5 bookings (`vehicle`/`pickupLocation`/`returnLocation` in
one query, rule 8) and renders the slot. `App\Http\Controllers\BookingController`
(core-owned, mirrors `OrderConfirmationController`'s `isOwner || hasValidSignature`
gate exactly) + `bookings.show` route + `Bookings/Show.tsx` close the public
booking-detail-page gap; `SendBookingConfirmationEmail` now computes a real
`confirmationUrl` (signed for guests, plain route for owners) and the email
template's previously-omitted CTA link is restored.

**Theming scope, deliberately bounded:** `AuthenticatedLayout.tsx` and
`Profile/Edit.tsx`'s own wrapper markup were retokenized as part of this
phase (justified — the new widget renders inside them, and a tokenized
widget inside an untokenized shell would be visibly inconsistent). **Not
touched, flagged instead of silently fixed or silently ignored:**
`resources/js/Components/{NavLink,Dropdown,ResponsiveNavLink}.tsx` (shared
Breeze components, also used by Login/Register, still have hardcoded
indigo/gray Tailwind classes) and `Profile/Edit.tsx`'s three Partials
(`UpdateProfileInformationForm`, `UpdatePasswordForm`, `DeleteUserForm`,
same hardcoded-color state). Both are real, pre-existing rule-3 violations,
out of this phase's agreed scope — left as a named, deliberate deferral for
a future theming sweep rather than expanded into or silently left
unmentioned.

**Verified end-to-end with real HTTP requests, not just the automated
suite (121 tests, Pint, Larastan, `tsc --noEmit --strict` all pass):** a
real `php artisan serve` process (with `APP_URL` temporarily pointed at the
serve address, then reverted — signed URLs bind the full host into the
HMAC, so generating one via `tinker`'s CLI context and validating it
against a mismatched real-request host is a guaranteed false-negative, not
a bug; caught and fixed mid-verification, not silently worked around) was
used to: create two real bookings (one guest, one registered user) through
`BookingCreator`, confirm both queued jobs, process them for real against
Mailtrap (`-d openssl.cafile=...`, same environment gap as the prior
phase — one transient failure was a genuine Mailtrap sandbox rate limit
from this session's send volume, resolved by `queue:retry`, not a code
defect); extract the actual signed URL and hit it with **no session
cookie** — 200, with the exact real booking data (Ford Focus, E2E Airport,
750.00/150.00 deposit) in the Inertia props; hit the same URL with no
signature (403) and a tampered signature (403); log in for real over HTTP
(cookie-jar + `X-XSRF-TOKEN`, Laravel's stateful-SPA CSRF flow) as the
booking's real owner and hit `/bookings/{id}` via the plain route — 200,
correct data (Kia Sportage, 1600.00/320.00 deposit); log in as a
*different* real user and confirm the same URL — 403; and hit `/profile`
as the owner, confirming `dashboardWidgets` contains exactly one
`Widgets/BookingHistory` entry with `recentBookings` correctly scoped to
only that user's own booking, not the guest's or the other user's. All test
data (bookings, vehicles, the shared location, both users, queued/failed
jobs, sessions) cleaned up afterward; `.env` and the dev server reverted to
their original state.

## Status-only booking cancellation (verified 2026-08-04)

**Pre-flight rule-13 check, as predicted, found exactly what was
expected — `BookingCancelled` had zero real dispatch sites, same shape as
`BookingConfirmed`'s pre-fix gap.** What wasn't predicted, and reshaped the
paired damage-reporting phase into its own separate prerequisite: `VehicleCheckedOut`/
`VehicleReturned` *also* have zero real dispatch sites anywhere in
application code — not a missing call on top of a working lifecycle, but no
real checkout/return lifecycle at all. `RelocateVehicleOnReturn`'s only-ever
invocation was a manual `VehicleReturned::dispatch()` in `tinker` during
Phase 5's own verification (confirmed by re-reading that section — it says
so directly). `BookingsTable`'s status badge colors and filter dropdown for
`checked_out`/`returned` are decoration for a lifecycle that doesn't exist
in code. Damage-reporting (explicitly "at pickup and return" per the domain
doc) was correctly pulled out to wait on that lifecycle being built for
real, as its own phase — not designed against events that never fire.

**A second, independent dependency found specifically for cancellation:**
the domain doc's "cancellation policy" is refund logic
(`booking.cancellationPolicy`, refund percentage by proximity to pickup),
not just a status flip. Checked whether there's real money to apply that to
— there isn't. `authorizeDeposit()` has zero callers in the real booking
flow (already known and deferred in the booking-confirmation-email phase),
and tracing that same gap forward found a second, previously-unstated
consequence: `ViewBooking`'s Release/Capture Deposit actions are
permanently invisible for every real booking today, since their visibility
gate needs an `authorized` `deposit_authorization` `Payment` row that
nothing in the live flow ever creates. Three separate discoveries — refund
policy, deposit-release visibility, deposit-capture visibility — all
rooting back to the same one undesigned deposit-gate decision, now a strong
signal that decision is the natural next dedicated phase.

**Scoped deliberately narrow, matching the resolved fork:** cancellation
ships as status-only. `ViewBooking`'s new "Cancel Booking" action (visible
only when `status === 'confirmed'`) sets `status = 'cancelled'` and
dispatches `BookingCancelled` for real — the first real dispatch site this
event has ever had. No refund computation, no cancellation email (that's a
real, separate future addition, same shape as the booking-confirmation
email but not built here — named explicitly in `docs/event-registry.md`
rather than left implied). Freeing the vehicle needed zero new logic:
`CoreAvailabilityCheckPipe`'s blocking statuses (`confirmed`,
`checked_out`) already correctly excluded `cancelled` since Phase 5.

**Verified to the same standard as the booking-history phase — real HTTP,
not just the automated suite (125 tests, Pint, Larastan all pass):** a real
guest booking was created through `BookingCreator` against a real dev-DB
vehicle/location; a second booking attempt for the identical vehicle and
dates was confirmed **genuinely rejected** (`VehicleNotAvailableException`)
*before* cancellation — proving the block was real, not assumed; the
booking was then cancelled via the exact same code the Filament button
runs; the identical second-booking attempt was retried and **succeeded**
this time (`status: confirmed`, a real new booking ID) — real proof a
cancelled booking's vehicle becomes bookable again for the same dates, not
inferred from reading `CoreAvailabilityCheckPipe`'s status list. Separately,
logged in as a real staff user over real HTTP (`X-XSRF-TOKEN` cookie-jar
flow) and confirmed `/admin/bookings` renders both booking rows with the
correct status. All test data (bookings, vehicle, location, staff user,
sessions) cleaned up afterward; the dev server stopped.

**Automated coverage:** 4 new tests in `BookingResourceTest` — the action
visible on a `confirmed` booking, hidden once already `cancelled`, sets
status and dispatches `BookingCancelled` with the correct booking state,
and a full `BookingCreator`-round-trip test proving the same-dates-rebookable
claim inside the automated suite (independent of the manual `tinker`/HTTP
walkthrough above — both prove it, neither substitutes for the other).

## The real booking-creation flow (verified 2026-08-04)

**The single most significant finding in this project so far — not for
complexity, but because "modeled but never consumed" finally applied to
the literal core purpose of the business, not a supporting mechanism.**
Sent as the pre-flight for what was framed as "the deposit-gate decision"
(deferred twice already, under the framing "sync vs async Stripe call
inside the transaction"). Tracing that framing all the way down found it
was subtly wrong in two compounding ways:

1. **A real deposit hold structurally requires a client-side step.**
   `authorizeDeposit()` only creates a Stripe `PaymentIntent`
   server-side (`requires_payment_method` in Stripe's own lifecycle); the
   local `Payment` row starts `'pending'` and only becomes `'authorized'`
   via the `payment_intent.amount_capturable_updated` webhook, which Stripe
   only fires after a customer confirms the PaymentIntent client-side
   (Stripe Elements/Payment Element, possibly 3DS). "Sync vs async
   backend call" was never the real question — there's no backend-only way
   to place a hold at all.
2. **There was no real booking-creation flow to gate in the first place.**
   Grepped every real caller of `BookingCreator` across the entire
   codebase: none, anywhere, outside `tinker` and tests. `Vehicles/Show.tsx`'s
   "Book this vehicle" button had no `onClick`/`href` — a dead Phase 4
   placeholder. Nine-plus phases of genuinely rigorous, well-tested work
   (availability, pricing, payments infrastructure, driver verification,
   booking history, cancellation) were all built and verified against a
   booking-creation path that only ever existed in `tinker`.

**Split into two phases rather than designing payment collection against a
UI that didn't exist:** Phase A (this one) builds the real public checkout
flow with zero change to `BookingCreator`'s actual behavior — still
immediate-confirm, no gate. Phase B (Stripe Elements + the real hold, and
finally resolving cancellation's deferred refund math and the invisible
admin deposit buttons) is deferred until Phase A is proven working, so it
adds a gate to something real instead of guessing at where a gate belongs.

**Built:** `Plugins\BookingEngine\Http\Controllers\BookingCheckoutController`
(`GET/POST /vehicles/{vehicle}/book`, registered from
`BookingEngineServiceProvider` — not core, since it must call
`BookingCreator`), `Bookings/Checkout.tsx` (price preview + guest/owner
contact form), and a real date-picker form replacing the dead button on
`Vehicles/Show.tsx`. Full details, including the route/page shape and
scope boundaries (no one-way return-location picker yet — the service
layer has supported it since Phase 5, the UI doesn't expose it), in
`docs/event-registry.md`'s new "Public Booking Checkout" section.

**A real bug found by real end-to-end verification, not by re-reading the
code:** the initial `store()` redirected every booking to a plain
`route('bookings.show', $booking)` URL — for a guest booking, neither
owner-authenticated nor signed, so the guest who just booked got a genuine
`403` clicking through their own confirmation redirect. Caught by actually
following the real HTTP `Location` header during verification. Fixed by
checking the source e-commerce project's own
`CheckoutController::confirmationUrl()`, which signs the post-checkout
redirect for guests — `store()` now does the same signed-vs-plain split
`SendBookingConfirmationEmail` already uses. Re-verified after the fix:
the identical guest curl session that previously 403'd got a real 200 with
correct data.

**Verified end-to-end with real HTTP, matching the standard used for
booking history and cancellation (132 tests, Pint, Larastan, `tsc --strict`
all pass):** a real vehicle/location seeded in the dev DB; a real
`GET /vehicles/{id}` page load confirming real vehicle data; a real
`GET .../book?pickup_at=...&return_at=...` price preview confirming exact
numbers (2 days × 350 → 700 total, 140 deposit); a real guest `POST`
creating booking #21 for real, redirecting to a signed URL that genuinely
loaded (`200`, correct vehicle/total/deposit); the confirmation email
genuinely queued and then sent through Mailtrap; a second real `POST` for
the identical vehicle/dates confirmed **not** to create a second booking
(DB still showed exactly one row for that vehicle) — proving the
availability block holds through the real public entry point, not just
`BookingCreator` called directly. All test data (booking, vehicle,
location, jobs, sessions) cleaned up afterward; dev server stopped.

**Automated coverage:** 7 tests in `BookingCheckoutTest` — price preview
accuracy, unavailable-dates preview, a full guest booking through real HTTP
(including asserting the redirect URL is genuinely signed and genuinely
loads), guest-contact-fields-required validation, authenticated users
skipping guest fields, double-booking rejection via the real controller
(not `BookingCreator` directly), and a 404 for a non-available vehicle.

## Phase B — the real deposit-gate (verified 2026-08-04)

**The pre-flight overturned its own framing from two prior deferrals, and
then found a real collision with an already-shipped, already-verified
rule-9 decision.** "Sync vs async Stripe call inside the transaction" —
the question this had been deferred under twice — was never the real
question: a real deposit hold structurally requires a client-side step
(Stripe's `payment_intent.amount_capturable_updated` webhook only fires
after the customer confirms via Stripe Elements, possibly through 3D
Secure); there is no backend-only way to place a hold at all. That
correction then surfaced the real design problem: Phase 5's
`CoreAvailabilityCheckPipe` explicitly decided `pending` bookings don't
block availability, because at the time `pending`→`confirmed` happened in
one atomic call with no real time gap. A real hold introduces the first
genuine gap in that transition — and the moment that gap exists, the
original decision stops being a neutral default and becomes an actual
race: two customers could both pass availability, both get a real card
hold placed by Stripe, and only one could ever reach `confirmed` — leaving
the loser with money held for a car they can never get.

**Resolved by revising the Phase 5 decision explicitly, not by building a
race-resolution mechanism.** `pending` now blocks availability while its
hold is still live (`hold_expires_at` in the future) —
`CoreAvailabilityCheckPipe`'s docblock documents the full reasoning for
the revision inline, not just the new behavior. This makes the double-hold
race structurally impossible: a second checkout attempt for the same
vehicle/dates is rejected by the ordinary availability check inside
`BookingCreator::createPending()` itself, before it ever reaches
`PaymentGatewayRegistry::get()` or `authorizeDeposit()` — there's no
"who wins" question to resolve, because there's only ever one live hold
attempt per vehicle/dates. A null `hold_expires_at` (the shape `create()`'s
immediate-confirm path would produce if it ever persisted a pending row,
which it doesn't) is deliberately excluded from blocking — a null expiry
has no defined "is this hold still live" answer, and the pipe never
guesses.

**A genuinely new mechanism, held to the same "first real consumer"
standard as `SlotRegistry`/`HasMinimumRole`:** the hold needs a real
expiry path, or an abandoned checkout (customer closes the tab mid-payment)
would lock the vehicle forever now that `pending` blocks. This project had
no scheduler configured anywhere before this — `bootstrap/app.php` gained
its first `withSchedule()` call, running `bookings:release-expired-holds`
(`Plugins\BookingEngine\Console\Commands\ReleaseExpiredBookingHolds`)
every minute. Verified as a real mechanism, not just "the code compiles":
`schedule:list` shows the real cron entry registered; a dedicated test
(`ReleaseExpiredBookingHoldsTest`) proves the command is genuinely
registered via `Artisan::all()`, and a real before/after availability
check (same standard as the cancellation phase's proof) confirms a
still-live hold blocks, then genuinely stops blocking once expired and the
command runs — not inferred from reading the pipe's logic.

**Migration approved and applied before any code was written** (rule 7):
`bookings.hold_expires_at` (nullable timestamp) plus a composite index on
`(status, hold_expires_at)`, added specifically because the reviewer asked
whether the expiry job's exact query shape (`WHERE status = 'pending' AND
hold_expires_at < now()`) would benefit from one before real booking
volume existed — confirmed yes and added in the same migration rather than
retrofitted later.

**`BookingCreator` gained `createPending()`/`confirmPending()` alongside
the unchanged `create()`** — `create()` still exists for callers that
genuinely don't need a payment gate (tests, `tinker`, any future
admin-initiated booking); the real checkout flow uses the new pair
instead. `confirmPending()` re-runs the availability check as
defense-in-depth even though nothing else could have raced past a live
hold by construction — this project's standing discipline is to re-verify
rather than assume (rule 9) — and is idempotent (a second call on an
already-confirmed booking is a safe no-op, guarding against a retried
`bookings.confirm` request double-firing the confirmation email).

**`PaymentGateway::syncAuthorizationStatus()`** (new interface method,
implemented in `StripeGateway`) closes a real timing gap: the client-side
confirmation can complete before the async webhook has actually been
delivered. Calling this once, synchronously, right after client-side
confirmation succeeds doesn't weaken the webhook path at all — both share
the exact same amount-cross-check and target-status logic
(`StripeGateway::applyIntentState()`), so whichever arrives first resolves
the row and the second is a safe no-op via the existing idempotency guard.

**Built:** `BookingCheckoutController::store()` now calls
`createPending()` + `authorizeDeposit()` and renders `Bookings/Payment.tsx`
(Stripe Elements via `@stripe/react-stripe-js`, `redirect: 'if_required'` —
Stripe's own recommended pattern, stays on-page for the common non-3DS
case) with a real `client_secret`; a new `confirm()` action + `bookings.confirm`
route synchronously verifies the hold via `syncAuthorizationStatus()` and
calls `confirmPending()`. If hold authorization itself fails, the pending
booking row is deleted rather than left orphaned.

**Verified end-to-end against real Stripe test-mode infrastructure, not
mocked (153 tests, Pint, Larastan, `tsc --strict` all pass):** a real
booking (#22) was created through the real checkout flow, producing a real
Stripe `PaymentIntent` (`capture_method: manual`); confirmed via a direct
API call with a real Stripe test payment method (`pm_card_visa`), after
which Stripe's own record showed `requires_capture`; `bookings.confirm`
was then hit for real, `syncAuthorizationStatus()` genuinely called
Stripe's API and updated the local row to `authorized`, and the booking
genuinely transitioned to `confirmed` with `hold_expires_at` cleared.
Separately, the double-hold race was proven impossible against real
infrastructure (not just the mocked test): a first real checkout for a
vehicle/dates created a genuinely live hold (booking #23); a second real
checkout attempt for the identical vehicle/dates was rejected with no
booking row created and no second Stripe call made — confirmed by
inspecting the DB directly, not by reading the code. Booking #23's hold
was then expired and released via the real scheduled command, and a
direct Stripe API retrieval confirmed the real `PaymentIntent`'s status is
`canceled` on Stripe's own servers. All test data (bookings, vehicle,
location, Stripe PaymentIntents, jobs, sessions) cleaned up afterward; dev
server stopped.
