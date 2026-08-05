# Domain Requirements — Car Rental Platform

What a real car rental platform needs, organized the same way the e-commerce
gap analysis was — by priority, so you can build incrementally rather than
attempting everything at once. This is a starting roadmap, not a spec to build
in one phase.

---

## HIGH — the platform doesn't function as a real rental business without these

**1. Fleet management (the equivalent of "catalog")**
- `Vehicle` model: make, model, year, category (economy/SUV/luxury/van),
  license plate, daily rate, photos, seat count, transmission type,
  fuel type, mileage, current status (available/rented/maintenance)
- Admin CRUD for the fleet, same shape as `ProductResource` before

**2. Availability + booking engine (THE core complexity of this domain)**
- A `bookings` table: vehicle_id, customer/guest identity, pickup_date,
  return_date, pickup_location, return_location, status, total_price
- The availability-overlap query: given a requested date range, does this
  vehicle have any existing CONFIRMED booking that overlaps it? This needs to
  be correct and explicitly tested — get this wrong and you double-book a
  physical car
- A booking calendar UI showing which dates are already taken per vehicle

> **⚠ Turnaround buffer — NOT a nice-to-have, must be configured before this
> platform accepts real production bookings.** The core overlap query (built
> Phase 5) intentionally ships with zero buffer between bookings — a car
> returned at 10:00 can be picked up again at 10:00 by the query's own logic.
> That's correct as a base primitive (buffer requirements vary per fleet/client),
> but it is not correct as a real operating policy: no real business can clean,
> inspect, and refuel a car in zero minutes. This is unlike loyalty points or
> multi-language support below — those are genuinely optional features; a
> turnaround buffer is a near-universal operational necessity. It's designed
> to bolt on cleanly as a second pipe on the `booking.availabilityCheck`
> filter (see `docs/event-registry.md`) specifically so it's cheap to add —
> but "cheap to add" is not the same as "already added." Confirm a buffer
> pipe is registered and configured before any real customer can book a real
> vehicle through this platform.

> **A real public checkout flow is now built — DONE 2026-08-04, but was
> genuinely missing until then.** Found while pre-flighting the
> deposit-gate decision: `BookingCreator` had zero real callers anywhere in
> the app across every phase before this one — every booking that existed
> was created via `tinker` or tests, and the "Book this vehicle" button on
> the vehicle page did nothing at all. `Plugins\BookingEngine\Http\Controllers\BookingCheckoutController`
> (`GET/POST /vehicles/{vehicle}/book`) is the first real fix — see
> CLAUDE.md's "real booking-creation flow" section. Still no turnaround
> buffer (the warning above still applies) and no return-location picker
> for one-way rentals in the checkout UI yet (the service layer has
> supported one-way since Phase 5; the new checkout flow defaults both
> pickup and return to the vehicle's home location).

**3. Pricing engine**
- Base daily rate, with duration-based discounts (e.g. 7+ days = 10% off,
  30+ days = 25% off) computed through the `booking.priceCalculation` filter
- Optional extras with their own pricing: GPS, child seat, additional driver,
  insurance tiers
- Security deposit handling (separate from the rental charge — often
  authorized/held, not charged, until return)

**4. Driver eligibility + verification**
- Minimum age requirement (often 21-25 depending on vehicle category)
- Driver's license upload + verification (manual admin review to start;
  automated document verification is a real future upgrade, not a v1 need)
- Some categories (luxury/sports cars) may have stricter age/experience
  requirements — this should be configurable per vehicle category, not
  hardcoded

**5. Payment**
- Deposit/hold at booking time, final charge at pickup or completion —
  decide this explicitly, it's a real business-model decision, not a
  technical default
- Same payment gateway pattern as before (CMI for Morocco, Stripe if serving
  international customers) — reuse the `PaymentGatewayRegistry` pattern
  exactly

> **Note on going live with Stripe:** Stripe requires the account-holding
> business to be legally domiciled somewhere Stripe operates (US/EU/UK/etc.)
> — a Morocco-domiciled business with no foreign entity cannot activate
> live Stripe payments, only test mode. Confirmed with the business owner
> (2026-08-03) that a foreign entity is already in motion, so this is not
> expected to block going live — but worth a quick re-confirmation at the
> actual go-live moment, since business circumstances can change between
> now and then. Phase 7's Stripe integration is built and fully verified
> against test-mode keys regardless of this status.

> **"Deposit/hold at booking time... decide this explicitly" — DONE
> 2026-08-04 ("Phase B").** The real public checkout flow
> (`BookingCheckoutController`) now creates a time-limited pending hold,
> authorizes a real Stripe deposit against it via Stripe Elements
> (`Bookings/Payment.tsx`), and only confirms the booking once that hold
> genuinely succeeds (`bookings.confirm`, `PaymentGateway::syncAuthorizationStatus()`).
> Verified end-to-end against real Stripe test-mode infrastructure, not
> mocked — see CLAUDE.md's "Phase B" section for full evidence.
>
> **A real, deliberate revision of Phase 5's availability decision was
> required to make this safe** — a genuine client-side confirmation gap
> (Stripe Elements, possibly 3D Secure) meant the original "pending doesn't
> block" rule would have let two customers both get a real card hold placed
> for the same vehicle/dates, with only one ever able to confirm. `pending`
> now blocks while its hold is still live; an abandoned hold is released by
> `bookings:release-expired-holds`, this project's first scheduled task.
>
> **What this closes, and what it does NOT close:** the invisible
> `ViewBooking` Release/Capture Deposit buttons are now genuinely reachable
> for a real booking (an `authorized` `deposit_authorization` Payment row
> now really exists). **Cancellation's refund policy
> (`booking.cancellationPolicy`) is still not built** — a real deposit now
> exists to refund against, but the actual refund-percentage-by-proximity
> logic itself is separate work, not a byproduct of the deposit-gate
> existing. Worth revisiting as its own item now that its blocker is gone.

**6. Pickup/return locations**
- Multiple locations if the business operates in more than one city/airport
- One-way rentals (pickup at Location A, return at Location B) — decide early
  whether this is supported, since it changes fleet availability logic
  (a car returned at a different location needs to "belong" there until
  moved back)

---

## MEDIUM — needed soon after launch, not blocking a first version

- **Order/booking confirmation email** — **DONE 2026-08-04.** `BookingConfirmed`
  now dispatches from `BookingCreator::create()`, handled by
  `App\Core\Listeners\SendBookingConfirmationEmail` → a queued `App\Mail\BookingConfirmation`,
  with a real working link (see below — the deferred public booking page is
  now built, so the email's CTA is no longer self-contained-only).
- **Customer account/booking history** — **DONE 2026-08-04.** The public
  `bookings.show` page deferred above is built (`App\Http\Controllers\BookingController`,
  owner-or-signed-URL gated, same pattern as the e-commerce build's
  `orders.confirmation`), and a `Widgets/BookingHistory` widget — the first
  real `SlotRegistry` consumer in this project — lists the user's 5 most
  recent bookings on `Profile/Edit.tsx`, linking to it. Verified end-to-end
  with real HTTP requests, not just automated tests — see CLAUDE.md.
  **Deferred, named explicitly rather than silently left inconsistent:**
  `resources/js/Components/{NavLink,Dropdown,ResponsiveNavLink}.tsx` and
  `Profile/Edit.tsx`'s three Partials (`UpdateProfileInformationForm`,
  `UpdatePasswordForm`, `DeleteUserForm`) still have hardcoded Tailwind
  colors (a pre-existing Breeze-scaffold rule-3 violation, out of this
  phase's scope since they're also shared by Login/Register) — a future
  theming sweep should retokenize these; `AuthenticatedLayout.tsx` and
  `Profile/Edit.tsx`'s own wrapper markup were already retokenized as part
  of this phase.
- **Checkout/return lifecycle — DONE 2026-08-05.** `ViewBooking`'s "Check
  Out" (`confirmed` → `checked_out`) and "Mark Returned" (`checked_out` →
  `returned`) actions are the first real dispatch sites `VehicleCheckedOut`/
  `VehicleReturned` have ever had — before this, `BookingsTable`'s
  `checked_out`/`returned` status badges/filter were UI for a lifecycle
  nothing implemented, and `RelocateVehicleOnReturn` (real, correct code
  since Phase 5) had only ever been invoked via a manual `tinker` dispatch.
  Both actions also sync `Vehicle.status` (`available` ↔ `rented`)
  automatically. Verified end-to-end: a real one-way booking walked
  through confirmed → checked out → returned via these real actions,
  confirming the vehicle disappeared from and reappeared in the public
  fleet listing at the right times, and that it was genuinely relocated to
  its real return location — see CLAUDE.md's "checkout/return lifecycle"
  section.
- **Damage/condition reporting at pickup and return — DONE 2026-08-05.**
  `ViewBooking`'s "Report Condition" action (optional, visible once
  `checked_out`/`returned`) logs a real `App\Models\DamageReport` —
  free-text description + photos, matching `DamageReported`'s existing
  shape exactly (no structured checklist built speculatively; a genuinely
  different, bigger data model if ever needed). Photos stored privately.
  Retrievable from the same booking's admin view (`BookingInfolist`'s
  "Condition / Damage Reports" section). No automatic consequence (deposit
  capture, maintenance transition) — those stay separate, manual staff
  decisions, matching this project's established precedent. Verified
  end-to-end with real HTTP — see CLAUDE.md.
  **Found and fixed along the way:** a real, live Hard Rule 1 violation in
  `ViewBooking.php` from the immediately preceding phase (a core class
  importing a plugin's DTO directly), caught during this phase's
  pre-flight before adding anything new to the same file.
- **Cancellation** — **DONE, including the refund policy, 2026-08-05.**
  `ViewBooking`'s "Cancel Booking" action (staff-only, confirmed booking →
  `cancelled`) dispatches `BookingCancelled` for real and frees the vehicle
  for other bookings on the same dates. `booking.cancellationPolicy`
  (`CoreCancellationPolicyPipe`) now computes a real refund percentage by
  proximity to pickup and resolves the held deposit automatically as part
  of cancelling — full release above the top tier, a real partial
  `captureDeposit()` below it (Stripe's own partial-capture behavior
  auto-releases the remainder in the same call, confirmed against Stripe's
  docs and a real test-mode API call). Config tiers
  (`cancellation_refund_tiers`) are explicitly flagged as placeholder
  business numbers pending real numbers from the business owner, same as
  every other config-driven policy in this project before real numbers
  existed for it. Verified against real Stripe test-mode infrastructure —
  see CLAUDE.md's "cancellation refund policy" section.

  **A live gap closed as part of this same phase, found by re-checking
  before building on top of it:** `ViewBooking`'s Release/Capture Deposit
  buttons had no booking-status gate at all — visible for any booking with
  a live authorized hold, including one that hadn't reached pickup yet
  (a real Phase-B-introduced consequence, not a hypothetical). Gated on
  `pickup_at->isPast()` as an **explicit interim proxy** — `checked_out`/
  `returned` statuses still don't exist on any real booking (see below),
  so gating on those instead would have made the buttons permanently
  invisible again for every ordinary clean return. Replace this proxy once
  the real checkout/return lifecycle exists.

  **The still-missing checkout/return lifecycle is no longer just a
  deferred nice-to-have — it has now actively distorted how two separate
  features had to be built** (this visibility gate, and damage-reporting's
  scope split above). Worth being the next dedicated phase.
- **Reviews — DONE 2026-08-05.** A `reviews` plugin (`Review` model,
  `is_verified_rental`/`is_approved` moderation, staff-only approve/reject),
  ported from the source e-commerce project with two deliberate
  adaptations rather than an unchanged copy: verified-rental requires a
  genuine `returned` booking (not just "paid" — the closest source
  concept, but the wrong bar for a rental review), and no
  `LayoutVariantRegistry` port (that mechanism was found to have never
  actually been built in this project at all, despite being documented as
  if it existed — see CLAUDE.md's "vehicle reviews" section). Displayed on
  `Vehicles/Show.tsx` via a new `vehicle.detailWidgets` Slot — the first
  Slot ever registered into a plugin-owned page rather than a core one.
  Verified end-to-end with real HTTP.
- **Analytics dashboard** (reuse the extensible widget-builder pattern —
  utilization rate per vehicle, revenue, booking volume are the rental
  equivalents of the e-commerce revenue/top-products widgets)

---

## LOW — real features, genuinely fine to defer

- Loyalty/repeat-customer discounts
- Multi-language support (French/Arabic, same consideration as before)
- A native mobile app (see the discussion on this — build the responsive web
  version first)
- Fleet maintenance scheduling/tracking (service due dates, etc.)
- GPS/telematics integration for real-time vehicle tracking

---

## A note on reused patterns — don't rebuild what already works

Nearly everything from the e-commerce build transfers with only data-model
changes, not architectural changes:
- Theme system → identical, zero changes needed
- Payment gateways → identical pattern, same registry
- Layout variants → identical mechanism, new regions
- Reviews → nearly identical, swap "product" for "vehicle/booking"
- Analytics dashboard → identical registry-based widget system, new data
  queries
- Role-based access (`HasMinimumRole`) → identical
- Guest checkout / order confirmation pattern → directly reusable for guest
  bookings

The genuinely new domain complexity is entirely in **availability + pricing +
driver verification** — that's where the real design thinking needs to go,
not in re-deriving patterns that already proved themselves.
