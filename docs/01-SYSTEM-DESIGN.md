# System Design — Car Rental Platform

Same architectural philosophy as the prior e-commerce build: a small, stable
core, every feature built as an independent plugin, nothing hardcoded that a
future client/feature might need to change. Every mechanism below is proven
across 26+ phases of a real build — this isn't theoretical.

---

## 1. Layered architecture

```
CORE / KERNEL           → FilterRegistry (Pipeline), SlotRegistry, PluginManager,
                           core Events, core models (User, Vehicle, Booking, Location)
FEATURE PLUGINS         → fleet-management, booking-engine, payments-*, driver-verification,
                           locations, pricing-rules, insurance-addons, reviews
PRESENTATION LAYER       → Theme engine (unchanged from e-commerce), layout variants,
                           Inertia pages rendered by core controllers
```

Same rule as before: **dependency direction only ever points downward.** Core
never references a plugin's namespace. Plugins never reference each other's
classes directly — only through Events/Pipeline/Slots, or a declared
dependency resolved through config.

---

## 2. Core Events (actions) — define these early, they're the contract every plugin builds against

```
BookingRequested       — customer submits a booking request (before confirmation)
BookingConfirmed        — booking accepted, payment/deposit captured
BookingCancelled         — by customer or admin
VehicleCheckedOut         — customer picks up the vehicle (fires at physical handover)
VehicleReturned            — customer returns the vehicle
DamageReported               — condition issue logged at return
DriverVerified                 — a customer's license/ID passed verification
PaymentCaptured                  — deposit or final charge succeeded
```

Document each in `docs/event-registry.md` as you build it — this is your
internal API contract, same discipline as before.

---

## 3. Named filters (Pipeline) — where pricing/availability logic hooks in

```
booking.priceCalculation    — base daily rate → discounts → extras → final price
booking.availabilityCheck    — is this vehicle actually available for this date range
booking.cancellationPolicy    — how much refund applies given how close to pickup
vehicle.listQuery               — fleet listing query, same pattern as product.listQuery before
```

---

## 4. The core domain difference from e-commerce — read this carefully

**E-commerce's core problem was "is there stock, and what does it cost."**
**Car rental's core problem is "is this SPECIFIC vehicle free for THIS date
range, and does the price change based on duration/season/extras."** This is
a materially different data problem:

- No simple `stock_quantity` — availability is a **date-range query**: does
  this vehicle have any overlapping confirmed booking in the requested
  pickup→return window?
- Pricing is **duration- and calendar-sensitive** — a 3-day rental often has a
  different daily rate than a 14-day rental (long-duration discounts), and
  weekends/high season may cost more. This needs a real pricing engine, not a
  single `price_cents` column.
- A booking has **two dates that matter as much as any product ever mattered**
  — get the availability-overlap query right and tested explicitly, the same
  level of care given to payment/order logic in the e-commerce build. A bug
  here means double-booking the same physical car, which is a much worse
  real-world failure than an e-commerce pricing bug.

---

## 5. Plugin anatomy — identical mechanism to before

Each plugin is a local Composer package with its own Service Provider,
migrations, Events/Listeners, Pipeline pipes, and (if needed) its own Filament
admin resources — exactly the same shape as `payments-stripe`,
`product-media`, etc. in the prior build. See `PROCESS-GUIDE.md` for the
`add-plugin` procedure, which transfers unchanged.

---

## 6. Layout variants — reused, applied to new regions

Same `LayoutVariantRegistry` mechanism. Likely regions for this domain:
`header`, `footer` (same as before), `vehicle-card` (equivalent of
`product-card`), `booking-calendar` (how date-range selection is presented —
a calendar widget vs. simple date pickers), `vehicle-gallery`. Build a region
only once a second real design exists for it — same discipline as before, no
premature swappability.

---

## 7. Folder structure — identical shape to the e-commerce project

```
/app/Core            kernel — FilterRegistry, SlotRegistry, PluginManager, core Events, core Models
/app/Http/Controllers  core-owned controllers rendering Inertia pages
/plugins               every feature, one local Composer package per plugin
/resources/js/Pages    Inertia pages
/resources/theme       token system (reused from prior project)
/config                plugins.php, site.php
/database/migrations   core migrations only
/docs                  this doc, design system doc, domain requirements, event registry
```
