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
- **Damage/condition reporting at pickup and return** — photos + a
  checklist, protects both the business and the customer. **Blocked on a
  real checkout/return lifecycle that doesn't exist yet** — found
  2026-08-04: `VehicleCheckedOut`/`VehicleReturned` have zero real dispatch
  sites anywhere in the app (only ever manually fired in `tinker`
  verification); `BookingsTable`'s `checked_out`/`returned` status
  badges/filter are UI for a lifecycle nothing implements. Building
  damage-reporting against events that never fire would repeat the exact
  mistake `BookingConfirmed` was found and fixed for — the real prerequisite
  (staff-facing check-out/check-in actions that genuinely dispatch these
  events and set `booking.status`) needs to be its own phase first.
- **Cancellation** — **DONE (status-only) 2026-08-04.** `ViewBooking`'s
  "Cancel Booking" action (staff-only, confirmed booking → `cancelled`)
  dispatches `BookingCancelled` for real and frees the vehicle for other
  bookings on the same dates — verified both by automated test and a real
  booking → blocked-rebooking → cancel → successful-rebooking walkthrough
  (see CLAUDE.md). **The refund half of "cancellation policy engine"
  (`booking.cancellationPolicy` — how much refund based on proximity to
  pickup) is NOT built**, deliberately: there's no real captured/held
  deposit on any booking to compute a refund against, since
  `authorizeDeposit()` has no caller in the real booking flow (same gap
  named in the confirmation-email item above). That same gap also leaves
  `ViewBooking`'s Release/Capture Deposit buttons permanently invisible for
  every real booking today — three separate symptoms of one undesigned
  deposit-gate decision, which is the natural next dedicated phase.
- **Reviews** (reuse the reviews plugin pattern from the e-commerce build
  almost unchanged — verified-rental instead of verified-purchase)
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
