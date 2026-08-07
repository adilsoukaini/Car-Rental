# Booking Checkout Foundation — Guest Checkout, Pickup/Return Details, Booking Confirmation Email

> **Adapted from e-commerce checkout-foundation.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ✅ **Guest checkout** — DONE. `BookingCheckoutController::store()` branches on auth: authenticated → `user_id = auth()->id()`, `guest_* = null`; guest → `user_id = null`, `guest_name`/`guest_email`/`guest_phone` from the checkout form. Real guest bookings verified end-to-end through the public checkout flow.
> - ✅ **Pickup/return date + contact capture** — DONE. `Bookings/CheckoutForm.tsx` captures `pickup_at`/`return_at` plus guest/owner contact fields; validation server-side.
> - ✅ **Secure booking confirmation access (fixes the IDOR-class gap)** — DONE, but via a **different mechanism than this doc's `booking_number`**: `BookingController::show()` at `/bookings/{booking}` guards with `isOwner || hasValidSignature`, and the post-checkout redirect uses a signed URL for guests. There is **no random `booking_number`** column — the id-based route + signed URL achieves the same "non-guessable / ownership-checked" goal this doc's Decision 4 is after.
> - ✅ **Booking confirmation email** — DONE. `App\Mail\BookingConfirmation` + `SendBookingConfirmationEmail` listener fire on `BookingConfirmed` (the real event, dispatched at the end of `BookingCreator::create()`), self-contained with vehicle/date/location/total/deposit inline and a signed-vs-plain confirmation link.
> - ❌ **Pickup/return location selection in the UI** — NOT DONE. The service layer has supported one-way return locations since Phase 5 (`pickup_location_id`/`return_location_id`, `RelocateVehicleOnReturn`), but the checkout UI does not expose a return-location picker — it shows the vehicle's current location only. This is the direct adaptation of this doc's "shipping address" section.
> - ❌ **"Track your booking" page (booking reference + email lookup)** — NOT DONE. Guests can only reach their confirmation via the still-valid signed link; there is no lookup page for a returning guest whose link has expired.
> - ❌ **Saved/reusable locations for logged-in customers** — NOT DONE (explicitly deferred, same as the source doc).

**Goal:** close the true launch-blockers from the gap analysis, plus the
confirmation email that closes the loop after a booking. These are bundled
because they're genuinely coupled, not just conveniently grouped: guest checkout
needs *some* way for a guest to identify themselves later (contact name/email),
and a secure guest confirmation page needs a non-guessable booking reference —
which also fixes the IDOR-class gap (any logged-in user can currently view any
booking by guessing the ID in the URL).

**This is the largest change to core `Booking` data since the availability
engine phase.** `bookings.user_id` is read by `BookingResource` (Filament), the
`BookingHistory` widget, and reviews' `VerifiedRentalChecker`. Treat this with
the same care as any core-model widening.

---

## Four decisions, recommended defaults stated — confirm before Step 1

**Decision 1 — Unify the phone field.** `bookings.guest_phone` already exists
(currently optional). Rather than adding a second `contact_phone` column, the new
pickup/return form populates this same field, and it becomes **required** for
every booking (every booking needs a contact number regardless of how it's
paying). Recommended: yes, reuse/rename, don't duplicate.

**Decision 2 — Confirmation email fires on `BookingConfirmed`, not
`PaymentCaptured`.** A customer expects "thanks for your booking" immediately at
checkout, not only once payment is later confirmed. Every booking gets the same
email at the same moment — confirmed, not paid. Recommended: `BookingConfirmed`,
for every booking regardless of payment method/status. *(Already how the real
listener is wired — it fires from `BookingCreator::create()`.)*

**Decision 3 — Defer guest-to-account linking.** After a guest completes checkout,
show a lightweight "Create an account to track bookings more easily" prompt — but
do NOT attempt to retroactively attach that completed booking to a newly created
account. The guest can still look up that booking via booking reference + email
regardless of whether they register later. Recommended: ship the simple prompt
only; defer real account-linking as its own future phase.

**Decision 4 — Booking reference is fully random, not derived from the ID.** Use
`Str::random()` (or similar), stored unique, completely unrelated to the
auto-increment `id`. This avoids leaking booking-volume information as a side
benefit, not just avoiding guessability. *(Not yet implemented — the current
confirmation flow uses a signed URL on the id-based route instead, which achieves
the security goal without the random reference. Building `booking_number` would
additionally enable the "track your booking" lookup page below.)*

**Tell me if you want anything different before Claude Code starts Step 1.**

---

## 1. Schema — partially implemented (guest columns exist; address columns don't)

```php
// modify bookings table
Schema::table('bookings', function (Blueprint $table) {
    $table->foreignId('user_id')->nullable()->change(); // was non-nullable, cascadeOnDelete
    // drop the existing cascadeOnDelete FK, re-add as nullOnDelete
    $table->string('guest_name')->nullable();        // ALREADY ADDED
    $table->string('guest_email')->nullable();       // ALREADY ADDED
    $table->string('guest_phone')->nullable();       // ALREADY ADDED (decision 1)
    $table->string('booking_number')->unique();      // random reference, decision 4 — NOT YET ADDED
    // pickup/return contact & address fields (the "shipping address" adaptation)
    $table->string('contact_name')->nullable();
    $table->string('contact_address_line1')->nullable();
    $table->string('contact_address_line2')->nullable();
    $table->string('contact_city')->nullable();
    // guest_phone becomes NOT NULL going forward (decision 1) — existing rows
    // need a backfill value (e.g. 'unknown') since historical bookings don't have one
});
```

**`user_id` nullable + `nullOnDelete`** fixes both the guest-checkout requirement
and the audit finding (account deletion currently cascades and destroys
booking/revenue history) — the same schema change serves both purposes. *The
nullable `user_id` change and the guest fields are done; the `nullOnDelete` swap,
`booking_number`, and contact-address columns are not.*

**Backfilling existing bookings**: every booking placed so far has a real
`user_id` (no guest bookings existed when the guest flow shipped) and no
contact-address data at all. Existing rows need: `booking_number` generated
retroactively (for consistency), and the contact-address fields either left null
with a "no address on file" admin display, or backfilled with a placeholder —
flag this choice explicitly before running the migration, don't silently pick one.

---

## 2. Guest checkout flow — DONE

- The `/vehicles/{vehicle}/book` route has no `auth` requirement (guest checkout
  is public)
- `BookingCheckoutController::store()`: if `auth()->check()`, `user_id = auth()->id()`,
  `guest_email = null`; else `user_id = null`, `guest_*` = validated values from the
  checkout form
- The booking flow itself is session/stateless per request — a guest never needs a
  session cart; each checkout creates its own pending booking
- Existing account-linked flows (reviews requiring login, booking history on
  `/profile`) are unaffected — a guest simply can't do those without registering,
  which is expected and fine

---

## 3. Pickup/return details capture — NOT YET IMPLEMENTED (the "shipping address" adaptation)

A required form section at checkout: contact name, phone (reusing `guest_phone`,
now required), address line 1, address line 2 (optional), city, postal code
(optional), country (default Morocco, but selectable). Validated server-side
regardless of guest/logged-in status — this is fleet-operational data, not
account data, so it belongs to the booking regardless of who placed it.

**Pickup/return location selection** is the other half of this section and is
the bigger gap: the service layer (`pickup_location_id`/`return_location_id`,
one-way relocation on `VehicleReturned`) supports it since Phase 5, but the UI
doesn't expose a return-location picker. Building it means a location dropdown
seeded from `Location::where('is_active', true)` and writing the chosen
`return_location_id` to the booking.

**Explicitly deferred, not this phase**: saved/reusable locations for logged-in
customers (a separate `addresses` table letting a customer pick a saved address
at checkout instead of retyping it). This phase captures the details on the
booking; reusing them across future bookings is its own future phase.

---

## 4. Booking reference + secure confirmation access — DONE (via signed URL); random reference NOT DONE

```php
// Booking model
protected static function booted()
{
    static::creating(fn ($booking) => $booking->booking_number = Str::upper(Str::random(10)));
}
```

*(This `booking_number` boot hook is NOT yet implemented. The security goal below
is already met a different way.)*

Confirmation/success page moves from `/checkout/success?booking={id}` (guessable,
no ownership check) to `/bookings/{booking}/confirmation` with a real ownership
guard:

```php
public function show(Booking $booking, Request $request)
{
    $isOwner = $request->user()?->id === $booking->user_id;
    $isGuestWithSignedLink = $request->hasValidSignature(); // for the immediate
                                                               // post-checkout redirect
    abort_unless($isOwner || $isGuestWithSignedLink, 403);

    return Inertia::render('Bookings/Confirmation', [...]);
}
```

Immediately after checkout, redirect using a **signed URL**
(`URL::temporarySignedRoute`, valid ~48 hours) so a guest reaches their own
confirmation page without needing to log in or re-enter anything — but that
signature expires, unlike the current permanent-looking success link.
**Already implemented** in `BookingController::show()` / `Bookings/Show.tsx` —
the guest redirect is signed, ownership is `user_id`-checked, and a different
logged-in user gets a real 403 (verified end-to-end).

**For a guest returning later** (days after, signed link expired): a simple
public "Track your booking" page — booking reference + email, matched against the
stored `booking_number`/`guest_email` pair, before showing anything.
**NOT implemented** — and it's blocked on `booking_number` existing.

**For logged-in customers**: ownership is just `booking.user_id === auth()->id()`,
same as any other authenticated resource — no signed link needed, and this also
becomes the real booking detail page the `BookingHistory` widget links to.

---

## 5. Booking confirmation email — DONE

```php
// app/Mail/BookingConfirmation.php
class BookingConfirmation extends Mailable implements ShouldQueue
{
    public function __construct(public readonly Booking $booking) {}
    // Booking is a core model — core Mailable referencing it is fine, no Hard Rule issue
}
```

```php
// app/Core/Listeners/SendBookingConfirmationEmail.php
class SendBookingConfirmationEmail implements ShouldQueue
{
    public function handle(BookingConfirmed $event)
    {
        $booking = Booking::find($event->bookingId); // primitive ID in the event, resolve here
        $recipient = $booking->user?->email ?? $booking->guest_email;
        Mail::to($recipient)->send(new BookingConfirmation($booking));
    }
}
```

Includes: booking reference/confirmation link, vehicle (make/model/license plate),
pickup and return dates and locations, total price, and deposit amount — and a
link to the confirmation page (the signed URL for guests, a plain link for
logged-in users). *Implemented and verified: real guest + owner bookings received
the email through Mailtrap with correct vehicle/total/deposit data.*

**You'll need real mail sending configured** (SMTP credentials or a transactional
email service) for this to actually deliver anywhere outside of local log-based
mail testing — same "ask, don't invent credentials" rule as Stripe in the payments
phase.

---

## 6. Consumers of `bookings.user_id` to check explicitly, not assume

- `BookingResource` (Filament) — must display `guest_*` fields when `user_id` is
  null, not show a broken/blank user reference *(implemented — the List/View show
  guest info)*
- `BookingHistory` widget — unaffected for logged-in customers; guest bookings
  correctly never appear there (a guest has no account to view it from)
- Reviews' `VerifiedRentalChecker` — unaffected; review submission already
  requires login, so this check's `user_id` query continues to work exactly as
  before for the accounts that can reach it
- Any future analytics/reporting — nullable `user_id` means "total customers"
  and "total bookings" are no longer the same denominator; worth a mental note
  for whenever the analytics dashboard gets extended

---

## 7. Build order

1. Confirm all 4 decisions above (note: the guest/email parts are already built)
2. Schema migration (section 1) — `booking_number` + contact-address columns +
   the `nullOnDelete` swap — ask before running, and explicitly decide the
   backfill approach for existing bookings before running it
3. ~~Guest checkout flow~~ — DONE (branch on auth status in
   `BookingCheckoutController::store()`)
4. Pickup/return details form + validation, plus the return-location picker
   (section 3)
5. `booking_number` generation + confirmation page with ownership guard (section 4)
   — the security-critical part, verify it carefully (the ownership/signed-URL
   guard itself already exists; add the random reference + "track your booking")
6. ~~`BookingConfirmation` Mailable + `SendBookingConfirmationEmail` listener on
   `BookingConfirmed`~~ — DONE
7. Update `BookingResource`, confirm `BookingHistory` and reviews still work
   correctly (section 6)
8. Verify, with real evidence:
   - Complete a full guest checkout (no login) — confirm the booking is created
     with `user_id = null`, `guest_*` set, pickup/return details stored,
     `booking_number` generated
   - Confirm the guest is redirected to their confirmation page via signed URL
     and can view it without logging in
   - Confirm a DIFFERENT logged-in user CANNOT view that guest booking by guessing
     its `booking_number` or a stale link (403) — the concrete proof the IDOR
     gap is actually fixed, not just moved
   - Confirm the booking confirmation email actually sends (check logs or a real
     test inbox) and contains correct vehicle/date/location/total details
   - Complete a full logged-in checkout, confirm `user_id` is set correctly,
     confirm the `BookingHistory` widget still shows it, confirm the customer
     can view their own booking detail page
   - Confirm `BookingResource` in Filament displays guest bookings correctly
     (showing `guest_*`, not a broken user link)
   - Delete a test user with existing bookings, confirm the bookings survive with
     `user_id` set to null rather than being deleted
