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

**Fires when:** Staff logs a damage/condition issue at pickup or return.
**Assumes:** Nothing about vehicle status — listeners decide whether this warrants moving the vehicle to `maintenance`.
**Use cases:** notify staff/admin, attach to the booking's record for deposit dispute resolution, flag the vehicle for inspection.

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

## Named Filters (Pipeline)

Registered via `App\Core\Support\FilterRegistry::register()`, run via `FilterRegistry::apply()`.

| Filter name | Purpose | Registered by |
|---|---|---|
| `booking.priceCalculation` | Base daily rate → duration discounts → deposit (extras not yet built) | booking-engine plugin (`CoreDurationDiscountPipe` + `CoreDepositPipe`, Phase 6) |
| `booking.availabilityCheck` | Is this vehicle actually available for this date range + pickup location | booking-engine plugin (`CoreAvailabilityCheckPipe`, Phase 5) |
| `booking.cancellationPolicy` | How much of a held deposit is refunded given how close to pickup | booking-engine plugin (`CoreCancellationPolicyPipe`, added 2026-08-05) |
| `booking.driverEligibilityCheck` | Is this driver eligible (age/category) to book this vehicle | driver-verification plugin (`CoreDriverEligibilityCheckPipe`, Phase 9) |
| `vehicle.listQuery` | Fleet listing query — filters/sorts applied to the base query | fleet-management plugin (Phase 4) |
| `vehicle.reviews` | Approved reviews + average rating for a vehicle's detail page | reviews plugin (`GetVehicleReviewsPipe`, added 2026-08-05) |

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
checks two things: (1) no `confirmed`, `checked_out`, or still-live-`pending`
booking on the same vehicle overlaps the requested range — exclusive-end
boundary, no turnaround buffer (see `docs/03-DOMAIN-REQUIREMENTS.md`'s
explicit warning that a buffer must be added as a second pipe before real
production use); (2) the vehicle's current `location_id` matches the
requested pickup location. `cancelled`/`expired` bookings never block. A
`pending` booking blocks ONLY while its hold is still live
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
`PriceCalculationRequest` (vehicle id, pickup/return datetimes). Each pipe
fills in more of the breakdown and calls `$next($breakdown)`. Order
matters and is enforced by `FilterRegistry::register()` priority —
`CoreDurationDiscountPipe` (priority 10) must run before `CoreDepositPipe`
(priority 20), since the deposit pipe reads `$breakdown->subtotal`.

**`CoreDurationDiscountPipe`** computes whole rental days (partial days
round UP — a 2 day 3 hour rental bills as 3 days, minimum 1 day) and
applies a **cliff/threshold** discount from
`config('booking-engine.duration_discount_tiers')` — the highest day-count
threshold met wins; discounts are not cumulative/graduated across tiers.

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
2. **No `LayoutVariantRegistry`/`LayoutSlot` port.** The source plugin
   renders 9 different per-client-theme review-display components via
   that registry — a real need there (6+ real client themes). This
   project has one real client theme; building that whole mechanism now
   would serve a hypothetical future need, not a real one (see the
   corrected "Layout Variant Regions" section above for the full finding).
   `Widgets/VehicleReviews.tsx` is a single tokenized component instead.

**`App\Models\Review`** is a core model (not plugin-owned), same
precedent as `DriverVerification` — the `reviews` plugin owns the
migration, business logic, and Filament resource, but the model lives in
`App\Models` so core's `ReviewSubmitted` event can reference it without
core ever importing the plugin's namespace (Hard Rule 1). Unique
constraint on `(vehicle_id, user_id)` — one review per customer per
vehicle, not per booking (a customer who rents the same car twice still
reviews it once).

**`vehicle.detailWidgets`** — the first Slot registered into a
**plugin-owned** page (`fleet-management`'s `Vehicles/Show.tsx`) rather
than a core one. `account.dashboardWidgets` (the first real slot overall)
renders into core's `Profile/Edit.tsx`; this proves the same mechanism
works when the host page itself belongs to another plugin —
`fleet-management` never references the reviews plugin by name, only the
named slot, exactly like core never references it.

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

**`vehicle.detailWidgets`** (added 2026-08-05, reviews phase) — see the "Vehicle Reviews" section above for the full mechanism. The first slot registered into a plugin-owned page rather than a core one.

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

**Status: planned, not implemented.** The source e-commerce project's
version exists to let each of its 6+ real client themes render a genuinely
different layout for the same feature (9 different review-display
components, for example) — a real, load-bearing need there. This project
currently has exactly one real client theme (plus one disposable
theme-swap proof file) — building `LayoutVariantRegistry`/`LayoutSlot` now
would mean constructing kernel infrastructure with only a hypothetical
future consumer, the exact thing `PROCESS-GUIDE.md` rule 6 exists to
prevent. Build it for real the first time a second real client theme
genuinely needs a different layout for the same feature — not before.
