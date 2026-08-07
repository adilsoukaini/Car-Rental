# Event Registry

This file is the source of truth for every Laravel Event that core dispatches.
Treat a constructor signature change as a **breaking change** to every plugin
that listens to it — update this doc in the same commit.

Plugins listen to these events from their `ServiceProvider::boot()`:
```php
Event::listen(BookingConfirmed::class, Listeners\MyListener::class);
```

Named filters and slots are documented at the bottom of this file.

---

## Core Events

### `App\Core\Events\BookingRequested`

| Property | Type | Description |
|---|---|---|
| `$booking` | `Booking` | The booking with `status = 'pending'` |

**Fires when:** A customer or guest submits a booking request, before confirmation/payment.
**Assumes:** The booking row is already persisted, availability has already been checked.
**Use cases:** notify staff of a new request, hold vehicle availability, send a "request received" email.

---

### `App\Core\Events\BookingConfirmed`

| Property | Type | Description |
|---|---|---|
| `$booking` | `Booking` | The booking with `status = 'confirmed'` |

**Fires when:** `Plugins\BookingEngine\Support\BookingCreator::create()` successfully
inserts a booking. Dispatched immediately, with **no payment/deposit gate** — this
project currently has no step that holds or captures a deposit before a booking is
confirmed (`Payment::authorizeDeposit()` from Phase 7 has no caller in the booking
flow; it's only ever invoked later, manually, from the admin `BookingResource`'s
Release/Capture actions). A prior version of this doc described a two-step
request → deposit-hold → confirm flow that was never actually built — corrected
2026-08-04 (see CLAUDE.md's booking-confirmation-email section) rather than left
inaccurate. Gating confirmation on a successful deposit hold is a real, undesigned
future decision (sync-in-transaction vs. async Stripe call, what happens to the
booking if the hold fails) — not something to assume is already covered here.
**Assumes:** `$booking->status` has already been updated to `'confirmed'` before dispatch.
**Use cases:** send confirmation email (`App\Core\Listeners\SendBookingConfirmationEmail`,
registered in `AppServiceProvider::boot()`), block the vehicle's calendar, notify staff.

---

### `App\Core\Events\BookingCancelled`

| Property | Type | Description |
|---|---|---|
| `$booking` | `Booking` | The booking with `status = 'cancelled'` |

**Fires when:** a staff member cancels a booking via `ViewBooking`'s "Cancel Booking" action (added 2026-08-04). **Staff-only for now** — no customer self-service cancellation exists yet; the doc previously said "by the customer or an admin," which described a customer-facing path that was never built. Correct this line again if that path is added later rather than assuming it's already covered.
**Assumes:** `$booking->status` has already been updated to `'cancelled'` before dispatch — the vehicle is automatically freed for other bookings covering the same dates, since `CoreAvailabilityCheckPipe`'s blocking statuses (`confirmed`, `checked_out`) already correctly exclude `cancelled` (no change needed there, verified 2026-08-04 with a real booking → cancel → same-dates-rebookable round trip).
**Deliberately status-only — no refund logic.** `booking.cancellationPolicy` (refund percentage by proximity to pickup, per `docs/03-DOMAIN-REQUIREMENTS.md`) is **not built**: it would need a real captured/held deposit to compute a refund against, and `authorizeDeposit()` has no caller anywhere in the real booking flow (see the booking-confirmation-email section above) — the same gap also leaves `ViewBooking`'s Release/Capture Deposit actions permanently invisible for any real booking today. All three (refund policy, deposit-release visibility, deposit-capture visibility) are blocked on the same undesigned deposit-gate decision, not three separate gaps.
**Use cases:** free up the vehicle's calendar (works today), send cancellation email (not built — no listener registered yet, same pattern as `BookingConfirmed`'s email but not ported for cancellation), trigger a refund workflow (blocked on the deposit-gate decision above).

---

### `App\Core\Events\VehicleCheckedOut`

| Property | Type | Description |
|---|---|---|
| `$booking` | `Booking` | The booking being picked up |

**Fires when:** staff clicks "Check Out" on `ViewBooking` (added 2026-08-05)
— this project's first real dispatch site for this event. Before this, it
existed only as a definition; no code anywhere ever fired it. Deliberately
no time gate (can't check out before `pickup_at`) — matches every other
staff action on this page (Cancel/Release/Capture), none of which are
gated on a scheduled time, since real-world pickups are routinely early
or late.
**Assumes:** Identity/license verification for this pickup has already happened (see `DriverVerified`).
**Use cases:** mark the vehicle as `rented` (done automatically by the
same action, not left for staff to separately update on the Vehicle admin
form — see `ViewBooking`'s docblock), start the rental clock, log
condition/photos at handover (deferred — damage-reporting still needs its
own data model).

---

### `App\Core\Events\VehicleReturned`

| Property | Type | Description |
|---|---|---|
| `$booking` | `Booking` | The booking being returned |

**Fires when:** staff clicks "Mark Returned" on `ViewBooking` (added
2026-08-05) — the first real dispatch site for this event too.
`RelocateVehicleOnReturn` (Phase 5) has listened for this since Phase 5,
but its only invocation before this was a manual `tinker` dispatch during
Phase 5's own verification — this is the first time it's fired by real
application code. Verified end-to-end through this real path (not
`tinker`): a real one-way booking, checked out then marked returned
through these actions, correctly relocated the vehicle to its real return
location.
**Assumes:** Return condition has been logged separately (see `DamageReported` if damage was found — still not built, see the domain doc).
**Use cases:** mark the vehicle as `available` (done automatically; the
"send to `maintenance` if damaged" branch is deferred until damage-
reporting exists — a clean return always goes back to `available` today),
finalize billing, release/charge the security deposit (`ViewBooking`'s
Release/Capture Deposit actions are now gated on `status === 'returned'`,
a real status check replacing the `pickup_at->isPast()` interim proxy the
cancellation-refund phase introduced one commit earlier).

---

### `App\Core\Events\DamageReported`

| Property | Type | Description |
|---|---|---|
| `$booking` | `Booking` | The booking the damage was logged against |
| `$stage` | `'pickup'\|'return'` | Whether this was logged at handover or at return |
| `$description` | `string` | Free-text description of the damage/condition issue |
| `$photoPaths` | `list<string>` | Storage paths of any photos attached to the report |

**Fires when:** staff uses `ViewBooking`'s "Report Condition" action (added
2026-08-05) — this event's first real dispatch site. Optional, not
mandatory — visible once a booking is `checked_out` or `returned`, but
deliberately not required before Check Out/Mark Returned can complete
(building that as a required step would have meant a real behavioral
change to those already-verified actions).
**Persisted as `App\Models\DamageReport`** (core model, migration owned by
the `damage-reporting` plugin — same core-model/plugin-migration split as
`Review`/`DriverVerification`) — the event alone was never durable enough
to satisfy "attach to the booking's record for deposit dispute
resolution" below; something has to be queryable later, not just an
ephemeral fired event. Free-text `description` + photos only — no
structured checklist (a genuinely different, bigger data model) was built
speculatively; this matches the event's own shape exactly. Photos are
stored on the `local` (private) disk, same treatment as
driver-verification's license uploads — this is staff-facing dispute
evidence, not public content.
**Assumes:** Nothing about vehicle status — deliberately has **no
listener** as of this writing. Whether a report warrants moving the
vehicle to `maintenance` or capturing the deposit stays a separate,
manual staff decision via the existing Capture Deposit / Vehicle-status
actions — matching this project's established "manual for damage, not
automatic on a lifecycle event" precedent. This is intentional pure data
capture, not another "modeled but never consumed" gap — stated explicitly
so a future session doesn't miscategorize it as one.
**Use cases:** notify staff/admin (not built), attach to the booking's
record for deposit dispute resolution (done — see `BookingInfolist`'s
"Condition / Damage Reports" section), flag the vehicle for inspection
(not built — a manual staff decision today).

---

### `App\Core\Events\DriverVerified`

| Property | Type | Description |
|---|---|---|
| `$user` | `User` | The user whose license/ID passed verification |

**Fires when:** An admin manually approves a customer's driver's license/ID verification — the only real caller is `ViewDriverVerification`'s "Approve" action (Phase 9).
**Assumes:** The verification record itself lives in the driver-verification plugin's own tables, not on core `User` — this event only signals the outcome.
**Use cases:** unlock booking for categories that require verified drivers, send an approval email.

---

### `App\Core\Events\PaymentAuthorized`

| Property | Type | Description |
|---|---|---|
| `$payment` | `Payment` | The `payments` row, `type = 'deposit_authorization'`, `status = 'authorized'` |

**Fires when:** A gateway confirms a deposit hold is authorized (funds reserved, not yet captured) — Stripe's `payment_intent.amount_capturable_updated` webhook event.
**Assumes:** The `Payment` row already exists (created by `authorizeDeposit()`) and its status has just transitioned from `pending`.
**Use cases:** notify the customer the hold succeeded, unlock vehicle checkout.

---

### `App\Core\Events\PaymentCaptured`

| Property | Type | Description |
|---|---|---|
| `$payment` | `Payment` | The `payments` row that just succeeded — `type` is `deposit_capture` or `final_charge` |

**Fires when:** A payment gateway confirms a successful capture — either a previously-authorized deposit being captured (damage) or a final rental charge succeeding.
**Assumes:** The `Payment` row's `status` has already been updated to `succeeded` before dispatch.
**Use cases:** update booking status, send a receipt.

**Note:** this event's shape changed during Phase 7 from carrying `(Booking $booking, string $type, float $amount)` (its Phase 1/2 placeholder shape) to carrying the real `Payment` model. Safe to change — nothing consumed the old shape yet.

---

### `App\Core\Events\PaymentFailed`

| Property | Type | Description |
|---|---|---|
| `$payment` | `Payment` | The `payments` row, `status = 'failed'` |

**Fires when:** A gateway reports a failed charge/authorization, OR the webhook handler's own amount cross-check finds a mismatch between what the gateway reports and what we computed server-side (treated as a failure, never silently accepted).
**Use cases:** notify the customer, alert staff on an amount-mismatch failure (this specific failure mode indicates something is wrong beyond a simple declined card).

---

### `App\Core\Events\PaymentReleased`

| Property | Type | Description |
|---|---|---|
| `$payment` | `Payment` | The `payments` row, `type = 'deposit_release'` |

**Fires when:** A previously-authorized deposit hold is released (cancelled) without capturing — the clean-return path.
**Assumes:** Distinct from `PaymentRefunded` — no money ever moved for a release, unlike a refund which reverses money that was actually captured.
**Use cases:** notify the customer their hold was released.

---

### `App\Core\Events\PaymentRefunded`

| Property | Type | Description |
|---|---|---|
| `$payment` | `Payment` | The `payments` row, `type = 'refund'` |

**Fires when:** A refund is issued for a previously-captured payment (deposit capture or final charge).
**Use cases:** notify the customer, update accounting records.

---

### `App\Core\Events\ReviewSubmitted`

| Property | Type | Description |
|---|---|---|
| `$review` | `Review` | The just-created review row |

**Fires when:** an authenticated user submits a review via `Plugins\Reviews\Http\Controllers\ReviewController::store()` — the reviews plugin's only real dispatch site for this event. Carries the `Review` model itself, not raw scalar ids, matching this project's convention for every other domain event (`BookingConfirmed`, `BookingCancelled`, etc.) — a deliberate adaptation from the source e-commerce project's equivalent event, which carries `reviewId`/`productId`/`userId`/`rating` as separate scalars.
**Assumes:** The review row is already persisted, with `is_approved = false` and `is_verified_rental` already resolved (via `VerifiedRentalChecker`) before dispatch.
**Use cases:** notify staff a review is awaiting moderation (not built — no listener registered as of this writing), analytics.

**Documentation note (corrected 2026-08-05):** this event previously had no entry in this section at all — it was only described in prose inside the "Vehicle Reviews" filters section further down this file, the only Core Event not given the same templated treatment (property table, "Fires when," "Assumes," "Use cases") every other one gets. Added here for consistency; no behavior changed.

---

## Named Filters (Pipeline)

Registered via `App\Core\Support\FilterRegistry::register()`, run via `FilterRegistry::apply()`.

| Filter name | Purpose | Registered by |
|---|---|---|
| `booking.priceCalculation` | Base daily rate → duration/loyalty discounts → deposit (extras not yet built) | booking-engine plugin (`CoreDurationDiscountPipe` + `CoreLoyaltyDiscountPipe` + `CoreDepositPipe`) |
| `booking.availabilityCheck` | Is this vehicle actually available for this date range + pickup location | booking-engine plugin (`CoreAvailabilityCheckPipe`, Phase 5) |
| `booking.cancellationPolicy` | How much of a held deposit is refunded given how close to pickup | booking-engine plugin (`CoreCancellationPolicyPipe`, added 2026-08-05) |
| `booking.driverEligibilityCheck` | Is this driver eligible (age/category) to book this vehicle | driver-verification plugin (`CoreDriverEligibilityCheckPipe`, Phase 9) |
| `vehicle.listQuery` | Fleet listing query — filters/sorts applied to the base query | fleet-management plugin (Phase 4) |
| `vehicle.reviews` | Approved reviews + average rating for a vehicle's detail page | reviews plugin (`GetVehicleReviewsPipe`, added 2026-08-05) |

### Fleet-listing filter/sort registries (`VehicleFilterRegistry` / `VehicleSortRegistry`, added 2026-08-07)

The storefront `/vehicles` page filters and sorts **server-side** through two
registries (mirrors of the e-commerce project's `ProductFilterRegistry` /
`ProductSortRegistry`). They live in core (`app/Core/Support/`), registered in
`AppServiceProvider::boot()`, and orchestrated by
`Plugins\FleetManagement\Http\Controllers\VehicleController::index()` —
filtering is applied as WHERE/ORDER BY clauses *before* pagination, so it
works across the whole fleet, not just the current page.

- **`VehicleFilterRegistry`** — `VehicleFilterProvider` instances, each
  declaring `id()` (query-string param name), `label()`, `uiType()`
  (`select`/`range`/`checkbox`), `options()`, and `apply(Builder, value)`.
  Currently registered: `category` (`VehicleCategoryFilter`) and `transmission`
  (`VehicleTransmissionFilter`), both case-insensitive.
- **`VehicleSortRegistry`** — `VehicleSortOption` instances, each declaring
  `id()`, `label()`, and `apply(Builder)`. Currently registered: `price_asc`,
  `price_desc`, `name_asc`.

A future plugin registers a new filter or sort from its own
`ServiceProvider::boot()` (e.g.
`VehicleFilterRegistry::register(new SeatCountFilter)`) and it appears in the
frontend FilterBar **with zero frontend changes** — the page renders controls
generically from the `availableFilters`/`availableSorts` Inertia props. Both
registries are static and get `flush()`ed at the top of `PluginManager::boot()`
(same accumulation guard as `FilterRegistry`/`SlotRegistry`).

The controller keeps free-text `search` (make/model `LOWER` LIKE) separate from
the registry — it's a text search, not a selectable filter.

### `booking.availabilityCheck` — result convention

Unlike a normal transform-and-pass filter, this one can **short-circuit**.
The value passed through is a `Plugins\BookingEngine\Support\AvailabilityCheckRequest`
(vehicle id, pickup/return datetimes, pickup location id, optional excluding-booking-id
for edit flows). A pipe that finds a blocking reason returns `false` directly
(not calling `$next` — standard Pipeline short-circuit). A pipe that finds no
issue calls `$next($request)`, passing the same object unchanged.
`FilterRegistry::apply('booking.availabilityCheck', $request)` therefore
returns either the original `$request` object (available — truthy) or
`false` (unavailable). Callers check `$result !== false`, not truthiness of
a boolean.

**`CoreAvailabilityCheckPipe`** (the base/first pipe, always registered)
checks three things: (1) no `confirmed`, `checked_out`, or still-live-`pending`
booking on the same vehicle overlaps the requested range — exclusive-end
boundary, no turnaround buffer (see `docs/03-DOMAIN-REQUIREMENTS.md`'s
explicit warning that a buffer must be added as a second pipe before real
production use); (2) the vehicle's current `location_id` matches the
requested pickup location; (3) the requested pickup `Location.is_active`
is true — a soft-disable for NEW bookings only, added in the "Locations
admin CRUD" phase (see CLAUDE.md); a deactivated location does not affect
any booking that already references it. `cancelled`/`expired` bookings
never block. A `pending` booking blocks ONLY while its hold is still live
(`hold_expires_at` in the future) — this is a 2026-08-04 revision of the
original "pending never blocks" rule; see the pipe's own docblock and
CLAUDE.md's "deposit-gate" section for why the revision was necessary.

**`Plugins\BookingEngine\Support\BookingCreator`** is the only sanctioned
way to create a `confirmed` booking — it locks the vehicle row
(`lockForUpdate()`) inside a DB transaction before re-running this filter
and inserting, closing the check-then-insert race between two concurrent
confirm attempts. A `Booking::create()` call anywhere else bypasses both
the lock and this filter entirely — don't do that for anything that needs
to actually hold a vehicle.

### `booking.priceCalculation` — result convention

A normal transform-and-pass filter (unlike `booking.availabilityCheck`,
this one never short-circuits). The value passed through is a
`Plugins\BookingEngine\Support\PriceBreakdown`, wrapping a
`PriceCalculationRequest` (vehicle id, pickup/return datetimes, optional
`userId` — null for guests). Each pipe fills in more of the breakdown and
calls `$next($breakdown)`. Order matters and is enforced by
`FilterRegistry::register()` priority — `CoreDurationDiscountPipe`
(priority 10) must run before `CoreLoyaltyDiscountPipe` (priority 15),
which must run before `CoreDepositPipe` (priority 20), since the deposit
pipe reads the final `$breakdown->subtotal`.

**`CoreDurationDiscountPipe`** computes whole rental days (partial days
round UP — a 2 day 3 hour rental bills as 3 days, minimum 1 day) and
applies a **cliff/threshold** discount from
`config('booking-engine.duration_discount_tiers')` — the highest day-count
threshold met wins; discounts are not cumulative/graduated across tiers.

**`CoreLoyaltyDiscountPipe`** (added 2026-08-05) applies a cliff/threshold
discount from `config('booking-engine.loyalty_discount_tiers')`, keyed on
the customer's count of prior `returned` bookings (guests, `userId ===
null`, are always exempt — same precedent as driver verification; only
completed rentals count, same precedent as the reviews plugin's
`VerifiedRentalChecker`; the booking being priced never counts toward its
own tier). **Does not stack with `CoreDurationDiscountPipe`** — whichever
of the two produces the higher discount percent wins outright and
replaces the other; the maximum discount on any booking is always exactly
one tier that was actually defined, never an unbounded combination of
both. See CLAUDE.md's "loyalty discounts" section for the full reasoning.

**`CoreDepositPipe`** sets the security deposit to a flat percentage
(`config('booking-engine.deposit_percentage_of_subtotal')`) of the
already-discounted subtotal. The deposit is a genuinely separate figure
from `$breakdown->totalPrice()` (the rental charge) — see
`docs/03-DOMAIN-REQUIREMENTS.md`'s explicit "separate from the rental
charge" wording. **Neither this filter nor `BookingCreator` charges
anything** — they compute numbers only; actually capturing a payment is
the separate Payment/gateway phase (Phase 7, `PaymentGatewayRegistry` /
`PaymentAuthorized` / `PaymentCaptured` events, below).

**Explicitly out of scope for this filter as of Phase 6:** extras pricing
(GPS, child seat, additional driver, insurance tiers) — these need their
own data model (an add-ons table, a booking-extras pivot), not a line in
this pipeline. `PriceBreakdown::totalPrice()` currently equals `subtotal`
exactly because there's no extras total yet to add; a future extras pipe
would add its own line and adjust `totalPrice()` accordingly.

### `booking.cancellationPolicy` — result convention

A normal transform-and-pass filter (like `booking.priceCalculation`, not
short-circuiting). The value is `App\Core\Support\CancellationPolicyRequest`
(moved here 2026-08-05 from `Plugins\BookingEngine\Support` — it's
consumed by both a core class, `ViewBooking`, and this plugin's filter
pipe, the same shape `DriverEligibilityCheckRequest` already exists to
solve; the original plugin-namespaced placement was a real Hard Rule 1
violation, see CLAUDE.md) — booking id, `pickupAt`, `cancelledAt`, mutable `refundPercent` defaulting
to 100). **`CoreCancellationPolicyPipe`** applies a cliff/threshold refund
percentage by whole days remaining until pickup — the highest threshold
met wins, not cumulative, same model as `CoreDurationDiscountPipe`'s
discount tiers. Days-until-pickup is computed directly from timestamps
(`floor((pickupAt - cancelledAt) / 86400)`), not `Carbon::diffInDays()`,
whose signed-difference convention has flipped across Carbon versions and
is easy to get backwards silently.

**This computes a refund percentage against a held deposit, not a
"refund" in the everyday sense of reversing a captured payment.**
`PaymentGateway::chargeFinal()` (the actual rental-total charge) has zero
real callers anywhere in this project — the only real money movement at
booking time is the deposit hold. So "how much refund" really means "how
much of the still-*held*, never-captured deposit to release vs. forfeit as
a cancellation fee." Applied in `ViewBooking`'s Cancel Booking action: 100%
refund calls `releaseDeposit()`; anything less calls `captureDeposit()`
with the forfeited amount — a manual-capture PaymentIntent's partial
capture automatically releases the uncaptured remainder in that same call
(confirmed against Stripe's own docs — *"A partial capture automatically
releases the remaining amount"* — and a real test-mode API call before
relying on it), so no second gateway call is needed.

**`config('booking-engine.cancellation_refund_tiers')` values are
explicitly flagged as placeholder business numbers**, unlike
`duration_discount_tiers`/`deposit_percentage_of_subtotal` (which had real
numbers from day one) — retune anytime, no code change needed.

### `booking.driverEligibilityCheck` — result convention

Same short-circuit convention as `booking.availabilityCheck` (not
transform-and-pass): the value is `App\Core\Support\DriverEligibilityCheckRequest`
(`userId` nullable, `vehicleCategory`, `pickupAt`). A pipe that finds the
driver ineligible returns `false` directly; a pipe that finds no issue
calls `$next($request)`. Deliberately defined in **core**, not inside
either plugin that uses it — `booking-engine`'s `BookingCreator`
constructs the request and calls this filter, `driver-verification`'s pipe
consumes it, and neither plugin may import the other's classes directly
(Hard Rule 2). Core is the only namespace both may depend on.

**`CoreDriverEligibilityCheckPipe`** (driver-verification plugin, Phase 9):
guest bookings (`userId === null`) are exempt — see
`docs/03-DOMAIN-REQUIREMENTS.md` and CLAUDE.md's driver-verification
section for the full reasoning (enforcing this for guests would require
either forcing account creation or a pending-then-admin-review booking
workflow that doesn't exist yet). A registered user booking a category
with no configured minimum age (`config('driver-verification.minimum_age_by_category')`)
is always eligible. A category WITH a minimum age requires an `approved`
`DriverVerification` whose age **at the booking's `pickup_at` date, not
"today"** meets that minimum — a driver who turns 21 the week after
booking but before pickup is correctly eligible; one who's 21 today but
the rental starts before their birthday is not.

**Verification is per-User, not per-Booking** (confirmed 2026-08-03) —
uploaded once, reviewed once, reused for every future booking. This was a
real fork: per-Booking would work for guests too, but requires an async
admin-review step to happen *before* a booking can be confirmed, which
conflicts with `BookingCreator`'s current immediate-confirm design (no
pending→reviewed→confirmed workflow exists). Per-User composes cleanly
with what's already built, at the cost of guest exemption above.

### `vehicle.listQuery` — result convention

A normal transform-and-pass filter (like `booking.priceCalculation`, not
short-circuiting). The value passed through is a plain
`Illuminate\Database\Eloquent\Builder` for `Vehicle`, pre-scoped to
`status = 'available'` with `location` eager-loaded — a pipe adds
`->where()`/`->orderBy()` calls and returns the same builder via
`$next($query)`, never executes it (the controller alone calls
`->paginate()`). Registered by `fleet-management`'s public
`VehicleController::index()` specifically so other plugins (e.g. a future
pricing-rules or insurance-addons plugin) can augment the fleet listing
query without `fleet-management` ever referencing them (Hard Rule 2).

**No pipe is currently registered against this filter** — confirmed via
`grep -rn "register('vehicle.listQuery'"`, zero results. This is a pure,
currently-unused extension point, not a gap: `FilterRegistry::apply()`
returns the input unchanged when no pipes are registered, so the fleet
listing works correctly today with nothing attached. Stated explicitly so
a future session doesn't mistake "no pipe yet" for "broken."

### `vehicle.reviews` — result convention

A normal transform-and-pass filter, called via
`FilterRegistry::applyWithContext()` (not the plain `apply()` every other
filter in this project uses) — the only filter that needs a real,
already-loaded model bound into the container for its pipe's constructor
injection (`GetVehicleReviewsPipe(Vehicle $vehicle)`), rather than a value
carried on the request DTO itself. The value passed through (and
returned) is a plain array: `vehicleId`, `averageRating`, `reviewCount`,
`reviews` (list of approved review data). The caller
(`VehicleController::show()`) seeds the array with safe zeroed defaults
(`averageRating: 0.0`, `reviewCount: 0`, `reviews: []`) before applying the
filter, so a vehicle with the `reviews` plugin disabled (or zero reviews)
renders correctly with no special-casing in the controller.

**`GetVehicleReviewsPipe`** (reviews plugin) returns only `is_approved`
reviews, latest first, with the average rating rounded to 1 decimal place
— an unapproved review is invisible to everyone except staff (via
`ReviewResource`).

## Vehicle Reviews (added 2026-08-05)

A near-mechanical port of the source e-commerce project's reviews plugin,
renamed for this domain (`Product` → `Vehicle`), with two deliberate
adaptations, not verbatim copies:

1. **Verified-rental eligibility is a real domain improvement, not a
   ported concept.** The source `VerifiedPurchaseChecker` requires only
   `Order.payment_status === 'paid'` — payment succeeded, delivery not
   required. `Plugins\Reviews\Services\VerifiedRentalChecker` requires a
   genuine `returned` `Booking` for that vehicle+user — a review is about
   the actual rental experience, which is only assessable once the rental
   has concluded, not once payment succeeded. Only possible now that
   `returned` is a real, reachable status (see the checkout/return
   lifecycle phase) — explicit test coverage proves the boundary (a
   `confirmed`/`checked_out` booking does NOT verify; only `returned`
   does).
2. **The review display WAS later ported to `LayoutVariantRegistry` — but
   for admin layout choice, not per-client-theme.** The source plugin
   renders 9 different per-client-theme review-display components via that
   registry — a real need there (6+ real client themes). This project
   deliberately did NOT port that at first (one real client theme; building
   a per-client-theme mechanism would serve a hypothetical need — see the
   corrected "Layout Variant Regions" section above for the full finding),
   so `Widgets/VehicleReviews.tsx` was a single tokenized component. That
   changed on 2026-08-07 (layout-variants phase): a second real *display
   arrangement* for reviews arrived — a compact inline list alongside the
   full card list — so the reviews region became the `reviewDisplay` layout
   variant (see the "Layout Variant Regions" section), rendering
   `Widgets/VehicleReviewsCardList` (default) or
   `Widgets/VehicleReviewsCompact` via `LayoutSlot name="reviewDisplay"` on
   `Vehicles/Show.tsx`.

**`App\Models\Review`** is a core model (not plugin-owned), same
precedent as `DriverVerification` — the `reviews` plugin owns the
migration, business logic, and Filament resource, but the model lives in
`App\Models` so core's `ReviewSubmitted` event can reference it without
core ever importing the plugin's namespace (Hard Rule 1). Unique
constraint on `(vehicle_id, user_id)` — one review per customer per
vehicle, not per booking (a customer who rents the same car twice still
reviews it once).

**Review display: `reviewDisplay` layout variant, not a SlotRegistry slot.**
Reviews were originally rendered through a `vehicle.detailWidgets`
SlotRegistry entry (`Reviews/VehicleReviews`) — the first slot registered
into a **plugin-owned** page (`fleet-management`'s `Vehicles/Show.tsx`)
rather than a core one, proving SlotRegistry works when the host page
belongs to another plugin. That slot is gone as of 2026-08-07: review
display moved to the core-owned `reviewDisplay` layout variant
(`LayoutVariantRegistry`, registered in `AppServiceProvider`), so an admin
can switch between the card-list and compact components. The reviews
plugin still owns the review *data* (the `vehicle.reviews` filter), the
store route, and the Filament resource; `fleet-management`'s
`VehicleController` shares `reviewsData` as a direct page prop and the
page renders it via `LayoutSlot name="reviewDisplay"`.

**`App\Core\Events\ReviewSubmitted`** carries the `Review` model, not raw
ids — a deliberate adaptation from the source project's version (which
carries `reviewId`/`productId`/`userId`/`rating` as scalars), matching
this project's own convention for domain events (`BookingConfirmed`,
`BookingCancelled`, etc.).

Review submission has no eligibility gate — matching the source pattern
exactly: any authenticated user can review any vehicle, `is_verified_rental`
is tracked and displayed as a badge, not required to submit. Unapproved
reviews are invisible to everyone except staff (via `ReviewResource`,
list + Approve/Reject-via-delete, same shape as `BookingResource`).

## Public Booking Checkout (the real booking-creation flow)

**`Plugins\BookingEngine\Http\Controllers\BookingCheckoutController`** (added
2026-08-04) is the first — and, as of this writing, only — real caller of
`BookingCreator` anywhere in this application. Before this controller
existed, every booking ever created in this project (across nine-plus
phases of availability, pricing, payments, driver-verification, history,
and cancellation work) was created via `tinker` or automated tests. The
"Book this vehicle" button on `Vehicles/Show.tsx` had no `onClick`/`href` —
a dead placeholder from Phase 4. See CLAUDE.md's "real booking-creation
flow" section for the full finding.

Two routes, registered from `BookingEngineServiceProvider` (not core —
core can never reference `BookingCreator`, a plugin class; same split as
`fleet-management` owning the public vehicle-browsing controller for the
core-owned `Vehicle` model):

- `GET /vehicles/{vehicle}/book` (`bookings.checkout`) — takes `pickup_at`/
  `return_at` query params, runs `booking.availabilityCheck` and
  `booking.priceCalculation` **read-only** (no booking created — this is a
  preview), renders `Bookings/Checkout` with the vehicle, dates, an
  `available: bool` flag, and the full price breakdown.
- `POST /vehicles/{vehicle}/book` (`bookings.store`) — validates
  guest contact fields (required only if `$request->user()` is null), calls
  `BookingCreator::createPending()` (not `create()` — see "Deposit-gate
  design" below, added 2026-08-04 "Phase B"), authorizes a real Stripe
  deposit hold against the resulting pending booking, and renders
  `Bookings/Payment` with a Stripe `client_secret` for the frontend to
  collect payment via Stripe Elements. Catches `VehicleNotAvailableException`/
  `DriverNotEligibleException` and turns them into real Laravel validation
  errors (not a 500); if hold authorization itself fails, the pending
  booking row is deleted rather than left as an orphaned, payment-less
  pending row.
- `GET /bookings/{booking}/confirm` (`bookings.confirm`) — the second step
  of Phase B's two-step flow, called once the customer has completed
  payment client-side (or Stripe redirects back here after a step like 3D
  Secure). Synchronously re-checks the hold's real status via
  `PaymentGateway::syncAuthorizationStatus()` — never trusts the client —
  and only then calls `BookingCreator::confirmPending()`.

**A real bug found by real end-to-end verification, not by reading the
code:** the initial `store()` redirected every booking to a plain
`route('bookings.show', $booking)` URL. For a guest booking (no `user_id`),
that URL is neither owner-authenticated nor signed — the guest who just
booked got a genuine `403` on their own confirmation page. Caught by
actually clicking through the real HTTP redirect during verification, not
assumed correct because the code "looked right." Fixed by checking the
source e-commerce project's own `CheckoutController::confirmationUrl()` —
it signs the post-checkout redirect for guests using the identical
`URL::temporarySignedRoute` pattern `SendBookingConfirmationEmail` already
uses for the email link. `store()` now does the same signed-vs-plain split.
Verified again after the fix: the exact same guest curl session that
previously got a 403 on the redirect target got a real `200` with correct
data afterward.

Deliberately narrow scope for this first real flow: pickup and return
location both default to the vehicle's home `location_id` — no UI to pick
a different return location for a one-way rental yet, even though
`BookingCreator`/`RelocateVehicleOnReturn` have supported one-way at the
service layer since Phase 5. Exposing that in the checkout UI is a
reasonable, real future addition, not built here to keep this phase's scope
to "wire the flow that never existed" rather than also redesigning it.

### Deposit-gate design (added 2026-08-04, "Phase B")

**The real deposit hold, closing the gap Phase A deliberately left open.**
`BookingCreator::createPending()` (pending status + a time-limited
`hold_expires_at`, `config('booking-engine.hold_ttl_minutes')`, default 15)
replaces `create()` for the public checkout flow — `create()` itself is
unchanged and still exists for callers that genuinely don't need a
payment gate (tests, `tinker`, any future admin-initiated booking).
`BookingCreator::confirmPending()` is the second step: re-runs the
availability check (defense-in-depth, not because anything else could have
raced past a live hold — see below) and only then flips the booking to
`confirmed` and dispatches `BookingConfirmed`. Idempotent — calling it
again on an already-confirmed booking is a safe no-op, guarding against a
retried `bookings.confirm` request double-firing the confirmation email.

**A real rule-9-level revision, made explicitly, not silently.**
`CoreAvailabilityCheckPipe` (Phase 5) originally excluded `pending` from
its blocking statuses — correct at the time, because `pending`→`confirmed`
happened in one atomic synchronous call with no real time gap, so the
question of "should an in-progress booking reserve the vehicle" never
arose. A real deposit hold structurally requires a client-side step
(Stripe's `payment_intent.amount_capturable_updated` only fires after the
customer confirms via Stripe Elements, possibly through 3D Secure) — the
first real gap in that transition. Once that gap exists, non-blocking
`pending` stops being a neutral default and becomes an actual race: two
customers could both pass the availability check, both get a real Stripe
hold placed on their card, and only one could ever reach `confirmed` —
leaving the loser with money held for a car they can never get.

**Resolved by making the race structurally impossible, not by resolving a
winner/loser after the fact.** A `pending` booking now blocks ONLY while
`hold_expires_at` is in the future (`NULL` — the shape `create()`'s
immediate-confirm path would produce if it ever persisted a pending row,
which it doesn't — is excluded; a null expiry has no defined "is this hold
still live" answer and the pipe never guesses). This means a second
checkout attempt for the same vehicle/dates is rejected by the ordinary
availability check inside `createPending()` itself, before it ever reaches
`PaymentGatewayRegistry::get()` or `authorizeDeposit()` — proven for real,
not just asserted: `BookingCheckoutTest`'s race test expects
`authorizeDeposit()` exactly once, so a second call reaching Stripe would
fail the test on the Mockery expectation, not just a row count. Verified a
second way against real Stripe test-mode infrastructure too — see
CLAUDE.md's "deposit-gate" section.

**`ReleaseExpiredBookingHolds`** (`bookings:release-expired-holds`) is this
project's first scheduled task (`bootstrap/app.php`'s `withSchedule()`,
`everyMinute()`) — the cleanup half. Without it, an abandoned checkout
(the customer closes the tab mid-payment) would lock the vehicle for those
dates forever, since a pending hold now genuinely blocks. Marks the
booking `expired` and releases its deposit authorization via the real
gateway (`releaseDeposit()`) if one was ever created.

**`PaymentGateway::syncAuthorizationStatus()`** (new interface method) —
re-checks the gateway's own live status for a still-`pending` authorization
and applies it locally, sharing the exact same amount-cross-check and
target-status logic `handleWebhook()` uses (`StripeGateway::applyIntentState()`
is the shared implementation). Exists because the client-side confirmation
step can complete before the async webhook has actually been delivered —
webhooks remain the durable long-term source of truth (a webhook arriving
after this synchronous check already resolved the row is a safe no-op,
same idempotency guard as always), but a customer waiting on their booking
to finalize shouldn't be stuck for however long webhook delivery takes.

**Stripe UX choice:** `redirect: 'if_required'` on `stripe.confirmPayment()`
— stays on the checkout page for the common non-3DS case, only redirects
when a payment method genuinely requires it. Stripe's own current
recommended pattern; avoids building a full redirect-and-return page for
the case that doesn't need one.

**Schema:** `bookings.hold_expires_at` (nullable timestamp, migration
`2026_08_04_182454_add_hold_expires_at_to_bookings_table`), with a
composite index on `(status, hold_expires_at)` backing
`ReleaseExpiredBookingHolds`'s exact query shape.

## Payment Gateways

Registered via `App\Core\Support\PaymentGatewayRegistry::register(PaymentGateway $gateway, string $pluginSlug)`, cross-checked against the `plugins` DB table the same way `PluginManager` gates everything else — a disabled plugin's gateway never appears in `PaymentGatewayRegistry::enabled()` even though the class itself still exists.

**`App\Core\Contracts\PaymentGateway`** — the interface every gateway implements. Unlike a single-order-single-payment e-commerce checkout, a booking has genuinely separate financial events: `authorizeDeposit()` (hold, don't capture), `captureDeposit()` (full or partial — e.g. only the damage cost), `releaseDeposit()` (cancel the hold, clean-return path), `chargeFinal()` (charge outright, not a hold), `refund()`, `syncAuthorizationStatus()` (added 2026-08-04 — see "Deposit-gate design" below). Check `supportsDepositHold()` before calling the deposit methods — not every gateway can do a real hold (see `payments-stripe` below for why this matters).

**`Plugins\PaymentsStripe\StripeGateway`** (Phase 7) — uses the **PaymentIntents API with `capture_method: manual`** for the deposit, specifically because Stripe **Checkout Sessions** (the pattern the source e-commerce project used) capture immediately and cannot represent "hold now, decide later." `chargeFinal()` uses a normal (automatic-capture) PaymentIntent. Webhook handling (`handlePaymentIntentEvent()`) follows the same hard-won pattern as the e-commerce project: verify the Stripe-Signature HMAC before trusting anything, an idempotency guard (only a `status = 'pending'` `Payment` row is acted on — a webhook delivered twice for the same event is a safe no-op), and an amount cross-check (the gateway-reported amount must match what our own pricing engine computed — a mismatch is logged and treated as a failure, never silently accepted).

**A real bug found and fixed during Phase 7:** `StripeGateway`'s `StripeClient` must be built **lazily**, not in the constructor. `PaymentGatewayRegistry::register(new StripeGateway, ...)` runs in `StripeServiceProvider::boot()` — i.e. on *every single request*, regardless of whether any payment operation is used on that request, the moment the plugin is enabled. Eagerly constructing `StripeClient` with an empty/unconfigured `STRIPE_SECRET` throws immediately, which took down every page on the site (confirmed: `/`, `/vehicles`, even `artisan tinker` all failed), not just payment-related ones. Fixed via a `stripe(): StripeClient` accessor that builds the client on first actual use (`??=`). Watch for this same class of bug in any future gateway plugin — anything a `ServiceProvider::boot()` constructs eagerly is a potential whole-site outage if its config isn't ready yet, not a contained failure.

**Not built in Phase 7 — CMI.** The source e-commerce project's `CmiGateway` has no hold/pre-auth capability at all (refund is unimplemented; it's a straight charge-now 3DS redirect) — it cannot fulfill the deposit-hold leg this domain needs. Given the business's primarily-international customer base (confirmed 2026-08-03), Stripe is the priority gateway; CMI (Morocco's domestic-card processor) is deferred, not built alongside Stripe "just in case."

**Going live note:** Stripe requires the account-holding business to be legally domiciled somewhere Stripe operates — see `docs/03-DOMAIN-REQUIREMENTS.md`'s Payment section for the full note (confirmed 2026-08-03 that a foreign entity is already in motion for this business).

**Verified against Stripe's real test-mode API (2026-08-03).** Test credentials (Stripe test-mode keys, Mailtrap sandbox mail credentials) were reused from the e-commerce project's `.env` for this project's local `.env` — real `sk_test_`/`pk_test_` keys, not live ones. `authorizeDeposit()` and `releaseDeposit()` were both called for real against `api.stripe.com` (not mocked): a real `PaymentIntent` was created with `capture_method: manual`, confirmed via a direct API retrieval to have the exact expected `amount`, `currency`, and `metadata`; then released, confirmed via a second API retrieval that Stripe's own record shows `status: canceled`. This closes the specific gap the Mockery-based test suite couldn't cover on its own (see CLAUDE.md's Phase 7 section for what changed).

## Slots

Registered via `App\Core\Support\SlotRegistry::register()`, rendered via the `SlotOutlet` React component in `resources/js/pluginComponentRegistry.tsx` (added 2026-08-04 alongside the first real slot — this file didn't exist before then; Phase 2 only established the PHP-side mechanism).

**`account.dashboardWidgets`** (added 2026-08-04, booking-history phase) — the first real slot in this project. Registered from `AppServiceProvider::boot()` (core-owned, not plugin-owned — matches the source e-commerce project's own `RecentOrders`/`MyWishlist` widgets being core account features, not plugin extensions). Rendered from `ProfileController::edit()` into `Profile/Edit.tsx`, passed `recentBookings` (the current user's last 5 bookings, batch-loaded with `vehicle`/`pickupLocation`/`returnLocation` in one query per rule 8) as props. Current consumer: `Widgets/BookingHistory` (`resources/js/Widgets/BookingHistory.tsx`), listing recent bookings linking to `bookings.show`.

**`vehicle.detailWidgets`** (added 2026-08-05, reviews phase; **removed 2026-08-07**, layout-variants phase) — was the first slot registered into a plugin-owned page rather than a core one, but is now gone: review display moved to the `reviewDisplay` layout variant (see the "Layout Variant Regions" section and the "Vehicle Reviews" section above).

**Real bug found and fixed while wiring this up — see CLAUDE.md's booking-history section for full detail.** `SlotRegistry`/`FilterRegistry`'s static state was never cleared between `PluginManager::boot()` calls, silently accumulating duplicate entries across every Application boot in the same PHP process (every PHPUnit test method boots a fresh Application in the same process; a persistent-worker deployment like Octane would hit the identical issue in production). This had been masked by luck for `FilterRegistry` — every `booking.*` pipe so far recomputes from immutable input rather than compounding, so duplicate execution produced the same final number — until `SlotRegistry` (which has no such luck; a widget genuinely renders N times) exposed it via an exact-count test assertion. Fixed by adding `flush()` to both registries, called at the top of `PluginManager::boot()`.

## Layout Variant Regions

**Corrected 2026-08-05 — this section previously overstated reality.** It
read "Registered via `App\Core\Support\LayoutVariantRegistry::register()`,
rendered via the `LayoutSlot` React component," phrased as if that
mechanism existed. It never did: `grep -rn "LayoutVariantRegistry" app/`
returns nothing — the class was never created in this project at all, in
either kernel phase. This is a sharper-edged version of this project's
recurring "modeled but never consumed" pattern (see CLAUDE.md) — every
prior instance was real code or a real column sitting unused; this one is
documentation asserting kernel infrastructure exists when it was simply
never written, which a future session could reasonably try to use and
find nothing there.

**Status: implemented since the Frontend Foundation Phase (2026-08-05),
for real consumers — not built speculatively.** The correction above
predicted it would only be built for a *second real client theme*. What
actually triggered it was a *second real layout* in this project's own
Stitch design source: the storefront has genuinely different vehicle-card
arrangements (Vertical / Horizontal Split) and — as of this phase — a
genuinely different fleet-listing page arrangement (inline search / sidebar
search). `LayoutVariantRegistry`/`LayoutSlot` now exist for real:

- **`vehicleCard`** (registered in `AppServiceProvider::boot()`) — the
  vehicle-card region on the homepage (`Home.tsx` via
  `LayoutSlot name="vehicleCard"`) and the fleet listing
  (`Vehicles/Index.tsx`). Variants: `vertical` →
  `Layout/VehicleCard/Vertical`, `horizontal-split` →
  `Layout/VehicleCard/HorizontalSplit`. These component-name strings are
  mapped to (lazily-imported) React components in
  `resources/js/layoutComponentRegistry.tsx` — add one entry there per new
  card variant.
- **`fleetLayout`** (registered in `AppServiceProvider::boot()`, added
  2026-08-07) — the fleet-listing *page* layout. Unlike `vehicleCard`,
  these variants are NOT mapped in `layoutComponentRegistry.tsx` and are NOT
  rendered via `LayoutSlot`: `Vehicles/Index.tsx` reads the active component
  name directly from the shared `activeLayoutVariants` prop and switches its
  render inline. Variants: `default` → `fleet-layout-default` (inline
  search + filter above a 3-column grid), `sidebar` →
  `fleet-layout-sidebar` (sticky search/filter sidebar at `md:w-1/4` beside
  the grid at `md:w-3/4`). Both share the same search/filter/sort state and
  client-side logic — only the arrangement differs.
- **`reviewDisplay`** (registered in `AppServiceProvider::boot()`, added
  2026-08-07, layout-variants phase) — how reviews render on the vehicle
  detail page (`Vehicles/Show.tsx`). Replaces the removed
  `vehicle.detailWidgets` SlotRegistry entry (see the "Vehicle Reviews"
  section). Rendered via `LayoutSlot name="reviewDisplay"`, mapped to
  lazily-imported React components in `layoutComponentRegistry.tsx` like
  `vehicleCard`. Variants: `card-list` →
  `Widgets/VehicleReviewsCardList` (full cards: author, rating, title,
  body — the default), `compact` → `Widgets/VehicleReviewsCompact`
  (inline stars + one-line body, no author/title). Both share the same
  "leave a review" form, so switching the variant never drops the ability
  to post a review. The `reviews` plugin slug is metadata for the admin
  picker; the components themselves are core-owned.
- **`checkoutStyle`** (registered in `AppServiceProvider::boot()`, added
  2026-08-07, layout-variants phase) — the booking checkout *page* layout
  (`Bookings/Checkout.tsx`). Like `fleetLayout`, these variants are NOT
  mapped in `layoutComponentRegistry.tsx` and are NOT rendered via
  `LayoutSlot`: `Checkout.tsx` reads the active component name directly
  from the shared `activeLayoutVariants` prop and switches its render.
  The checkout form state (`useForm`), submit handler, availability
  warning, form content, and price summary card are all extracted into
  shared pieces (`CheckoutForm.tsx`, `CheckoutSummary.tsx`,
  `checkoutShared.ts`) used by both arrangements. Variants:
  `sidebar-flow` → `checkout-sidebar` (the original 2-column design: form
  left, sticky summary right, fixed mobile bottom bar — the default),
  `vertical-stack` → `checkout-vertical` (single centered `max-w-2xl`
  column, summary card stacked below the form, no sticky sidebar and no
  mobile fixed bar).

The admin switches any region on the Layout Variants page
(`app/Filament/Pages/LayoutSettingsScaffold.php`), which persists the choice
in `layout_settings` (`slot_name` → `active_variant_id`). With no DB row
yet, `LayoutVariantRegistry::activeComponentFor()` falls back to the first
registered variant — `vertical` for `vehicleCard`, `default` for
`fleetLayout`, `card-list` for `reviewDisplay`, `sidebar-flow` for
`checkoutStyle`.

## Analytics Dashboard (added 2026-08-05)

**No custom registry — uses Filament's own native widget system
instead.** The source project's "extensible widget-builder pattern"
(`DashboardWidgetRegistry`, a `DashboardWidgetTemplate` contract, a
persisted `DashboardWidgetInstance` model, a real builder UI) is genuine
infrastructure *there*, serving multiple independent plugins that compete
for dashboard space. This project has no such need — a handful of fixed
widgets is the actual requirement. `App\Providers\Filament\AdminPanelProvider`
already had `->discoverWidgets(in: app_path('Filament/Widgets'), ...)`
wired since Phase 4's scaffolding, configured but never given a real
widget — the same shape as `SlotRegistry` before booking-history, just
Filament's own default. Three widgets now live in `app/Filament/Widgets/`,
each extending a Filament-native base class
(`StatsOverviewWidget`/`ChartWidget`/plain `Widget`), auto-discovered with
zero additional registration.

**`BookingStatsOverview`** — "Total Booking Value", not "Revenue". The
source project's equivalent metric sums real captured payments
(`Order.payment_status === 'paid'`); this project's `PaymentGateway::chargeFinal()`
has zero real callers anywhere, so no booking's `total_price` has ever
actually been charged to a customer — the only real money movement is
the deposit hold. Counts only `confirmed`/`checked_out`/`returned`
bookings — what's currently, validly on the books.

**`BookingVolumeChart`** — a 30-day daily count, deliberately counting
`cancelled` bookings too (unlike the stats widget) — this answers "how
many bookings did we actually get, regardless of later cancellation," a
different question from "what's currently committed." Both this and the
stats widget exclude `pending`/`expired` — an abandoned mid-checkout
attempt never became a real booking.

**`VehicleUtilizationTable`** — booked days ÷ a fixed 30-day window, per
vehicle, computed in exactly 2 queries regardless of vehicle count (all
vehicles, then all overlapping bookings across every vehicle at once,
aggregated in PHP — rule 8). A named, real simplification: the
denominator is the full window for every vehicle, not reduced for time
spent in `maintenance` — this project has no historical vehicle-status
log to reconstruct "days actually available" after the fact.

**Verification boundary, stated explicitly:** Filament widgets default to
`$isLazy = true` — real content only renders via a follow-up child-
Livewire-component request that neither a plain `curl` GET nor
`Livewire::test()` against the *parent* Dashboard page ever triggers
(confirmed by testing both). The authoritative test target is each
widget's own Livewire component directly, which is what this phase's
automated tests do.

## Theme System (added 2026-08-05)

Not an Event/Filter/Slot, but documented here for the same reason as
those mechanisms (Hard Rule 5) — it's a real internal API contract other
code depends on. Ported from the e-commerce project as a domain-agnostic,
one-time copy (colors/fonts/radius/shadow tokens don't know or care
whether they're theming cars or products). Phase 3 only built the
file-based layer (`resources/theme/*.ts`, selected via `ACTIVE_THEME` in
`.env`) — this is the centralized, admin-driven layer on top of it.

**`App\Models\Theme`** — `themes` table (`name`, `slug` unique, `data`
json, `is_active` bool). **`App\Core\Support\ThemeManager::resolveActive()`**
returns the active row's `data`, or `ThemeManager::defaultData()` (a PHP
constant mirroring `resources/theme/clients/default.ts`) if no row is
active yet — this fallback is what keeps `HandleInertiaRequests::share()`
safe even before the table is seeded. `ThemeManager::activate($id)` wraps
the single-active-row swap in a transaction, so there's never a moment
with zero or two active rows.

**`App\Core\Support\ThemeSchemaRegistry`** — `registerField($path, $type,
$required)` (registered in `AppServiceProvider::boot()` for every field on
the `Semantic` TS interface), `validate($data)` returns a
`ThemeValidationResult`. Keyed by dot-path in an associative array, not
appended to a list — re-registering the same path on every boot is
naturally idempotent, unlike `FilterRegistry`/`SlotRegistry`'s static-array
accumulation bug (see the kernel-fix section above); no `flush()` needed
here.

**`App\Core\Support\ContrastChecker`** — WCAG 2.1 contrast ratio (4.5:1
AA minimum) on the three onX/X color pairs. Warns (not blocks) on upload
via a persistent Filament notification if a pair fails; on activation, the
confirmation modal shows the failures inline and still requires an
explicit confirm — never silently allows an illegible theme live, and
never silently blocks one either (a client's real brand colors might
technically fail AA and that's still their call to make).

**`App\Filament\Resources\Themes\ThemeResource`** (Admin-only,
`HasMinimumRole`/`Role::Admin`) — upload a JSON file, see a live
swatch/font preview rendered from the actual uploaded `data` (not a
static image), activate. `ThemeResource::parseAndValidateUpload()` reads
the uploaded file, JSON-decodes it, and runs it through
`ThemeSchemaRegistry::validate()` before it's ever persisted.

**`HandleInertiaRequests::share()`** now shares `themeData` =>
`ThemeManager::resolveActive()` on every request. **`resources/js/app.tsx`**
no longer statically imports the file-based `semantic` as `ThemeProvider`'s
data — it reads `themeData` from the initial page's Inertia props, holds
it in `useState` (outside the Inertia component tree, where `usePage()`
is unavailable — same reasoning `ThemeProvider`'s own docblock already
stated), and re-syncs on every `router.on('navigate')` event. This is
what makes an admin activating a theme take effect on a visitor's very
next navigation, with zero rebuild — verified with real Playwright
screenshots (upload → activate → the public fleet listing's background
and card styling visibly changed between two screenshots with no
`npm run build` run in between).

**Real regression found and fixed during this phase:** `HandleInertiaRequests`
now unconditionally queries the `themes` table on every single request —
`tests/Feature/ExampleTest.php` (the original, untouched Breeze stub test)
had no `RefreshDatabase` trait, since it never previously needed a
database at all, and broke with `SQLSTATE[HY000]: ... no such table:
themes`. Fixed by adding `RefreshDatabase` to that test (not by weakening
the middleware) — `ThemeManager::resolveActive()` already degrades
correctly to `defaultData()` against an empty (but existing) table.

**Seeded 2026-08-05** (`database/seeders/ThemeSeeder.php`): the two
themes that already existed as files — "Default" (byte-identical to
`ThemeManager::defaultData()`, marked active) and "Demo Rentals
(swap-proof, not a real client)" (byte-identical to
`client-swap-proof-DISPOSABLE.ts`'s existing values, including its own
unchanged Poppins display font — only the *Default* theme's font tokens
were updated to Space Grotesk/Inter/JetBrains Mono, per this phase's font
decision; the disposable proof theme was deliberately left as-is).
