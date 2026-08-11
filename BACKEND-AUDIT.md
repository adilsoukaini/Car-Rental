# Backend Audit — Database, API, Concurrency, Resilience

Systematic audit of the Car Rental backend against common production-readiness
failure modes. Conducted 2026-08-11 by deep-reading controllers, services, migrations,
middleware, configs, and plugins.

---

## Summary

| Severity | Count | Area |
|---|---|---|
| 🔴 HIGH | 10 | Data corruption, money bugs, silent failures |
| 🟡 MEDIUM | 7 | Performance, missing safety nets |
| 🟢 LOW | 8 | Code quality, minor gaps |

---

# 🔴 HIGH Severity

## H1. Queue worker + scheduler never run (all async features broken)

No `php artisan queue:work` or `schedule:run` cron is configured anywhere —
`Dockerfile`, `docker-compose.yml`, and the deployed environment all use
`php artisan serve` (single-threaded dev server).

**Impact:** Every queued email, every push notification, and the
`bookings:release-expired-holds` cron job (runs every minute) **silently
never executes**. Expired pending holds block vehicles permanently.
This is the single biggest production-readiness gap.

**Affected features:**
- Booking confirmation emails (`BookingConfirmation`, `BookingCheckedOut`, etc.)
- Push notifications (`SendPushNotificationOnBookingEvents`)
- Expired hold release (`ReleaseExpiredBookingHolds`)
- Welcome emails

**Files:** `Dockerfile`, `docker-compose.yml`, `bootstrap/app.php:22-30`,
`config/queue.php`

## H2. ReleaseExpiredBookingHolds races the confirmation flow — can corrupt booking status

`plugins/booking-engine/src/Console/Commands/ReleaseExpiredBookingHolds.php:30-65`

The cron selects `WHERE status='pending' AND hold_expires_at < now()` with
**no lock**, then per booking calls Stripe `releaseDeposit()` and flips status
to `expired` **without re-checking whether the booking was already confirmed**.

**Race scenario:**
1. Customer completes payment → `confirmPending()` is about to commit
2. Cron runs concurrently → sees `pending`, calls `releaseDeposit()`
3. `confirmPending()` commits → booking is now `confirmed`
4. Cron's `$booking->update(['status' => 'expired'])` **overwrites confirmed → expired**
   AND the deposit was released on a now-confirmed booking → 🔴 **money + data corruption**

**Fix:** `lockForUpdate()` on the booking row, re-check `status='pending'` atomically
in the update (`UPDATE bookings SET status='expired' WHERE id=? AND status='pending'`),
add `withoutOverlapping()` on the schedule, and wrap the per-booking loop in try/catch.

## H3. Payment authorization never transitions — double-capture/release possible

`plugins/payments-stripe/src/StripeGateway.php:86-124`

`captureDeposit()` and `releaseDeposit()` create a new `deposit_capture`/`deposit_release`
row but **never update the original `deposit_authorization` row's status**. It stays
`authorized` forever.

**Consequence A — repeatable bug:**
`ViewBooking::activeAuthorization()` (line 209) filters `status = 'authorized'`.
After a deposit is captured or released, the action buttons ("Capture Deposit",
"Release Deposit") remain visible and clickable. A second click calls Stripe on
an already-settled PaymentIntent → unhandled exception → 🔴 **Filament 500 error**.

**Consequence B — concurrent race:**
Two admins click Capture and Release simultaneously → both read `authorized`,
both call Stripe, one succeeds, the other throws.

**Fix:** Make the authorization a proper state machine — atomically update
`UPDATE payments SET status='captured' WHERE id=? AND type='deposit_authorization' AND status='authorized'`
inside a transaction, and gate the UI buttons on the actual current state.

## H4. Admin check-out / mark-returned not atomic — booking+vehicle can diverge

`app/Filament/Resources/Bookings/Pages/ViewBooking.php:131-167`

`checkOutAction()` updates the booking to `checked_out`, then updates the vehicle to
`rented` as **two separate auto-commit statements**. `markReturnedAction()` does the
same for `returned` / `available`. No `DB::transaction`, no lock, no conditional
`WHERE status=?` guard.

**Impact:** A mid-operation crash (or concurrent admin action) leaves booking and
vehicle status permanently inconsistent. The vehicle fleet listing filters on
`WHERE status='available'` — if the vehicle update fails after the booking update,
the vehicle is permanently removed from the fleet.

**Fix:** Wrap both updates in `DB::transaction` with `lockForUpdate()` on both
rows, add `WHERE status=?` conditional updates, standardize lock ordering
(vehicle → booking) to match `BookingCreator`.

## H5. BookingCancellationService has no lock

`app/Core/Support/BookingCancellationService.php:46-106`

`cancel()` does `if ($booking->status !== 'confirmed') throw` then
`$booking->update(['status' => 'cancelled'])` — classic check-then-act with
no transaction and no lock. Two concurrent cancel requests can both pass
the status check and both release/capture the deposit.

Also, the booking status update and the money resolution are **not atomic** —
a crash between updating the booking and calling the gateway leaves a
cancelled booking with a still-held deposit.

**Fix:** `DB::transaction` + `lockForUpdate()` on the booking row.

## H6. Booking creation has no idempotency — retries create orphaned bookings

`plugins/booking-engine/src/Http/Controllers/BookingCheckoutController.php:175`

`store()` reads no idempotency key. A customer whose response drops (or who
clicks twice) has no way to recover the first booking. The first attempt
succeeds and creates a pending booking + Stripe PaymentIntent, the second
gets 422 "no longer available" — and the customer never learns the first
booking's ID or Stripe client_secret. The abandoned pending booking and
Stripe intent sit until the hold expires (15 min).

**Fix:** Generate a client-side `Idempotency-Key` on the checkout page,
store it in a DB column, and return the existing booking when the same key
is presented on retry.

## H7. Stripe webhook has no event-ID dedup — concurrent deliveries double-fire events

`plugins/payments-stripe/src/StripeGateway.php:204-220`

Webhook handler deduplicates on payment `status='pending'`, not on Stripe event ID.
If Stripe redelivers the webhook before the first handler commits its status
update, both see `pending` and both transition the row and fire domain events
(`PaymentAuthorized`/`PaymentCaptured`/`PaymentFailed`) **twice**.

Today no listener is registered for those events (impact is benign). The moment
a listener is added (email, reconciliation), a burst of duplicate webhook events
double-fires it.

**Fix:** `DB::transaction` + `UPDATE ... WHERE status='pending'` (atomic
compare-and-set), plus a `stripe_webhook_events` table tracking processed event IDs.

## H8. StripeGateway has zero retry and zero timeout control

`plugins/payments-stripe/src/StripeGateway.php:48`

`new StripeClient(config('payments-stripe.secret'))` sets no `maxNetworkRetries`.
The SDK default is `Stripe::$maxNetworkRetries = 0` — a single transient network
blip during capture/release/refund throws unhandled. The SDK default timeout is
**80 seconds** — during a Stripe outage, every synchronous `confirm()` request
blocks for up to 80s.

**File also referenced:** `BookingCheckoutController.php:287` (synchronous
`syncAuthorizationStatus()` call on the request path).

## H9. Email + push listeners have no retry config → can retry-storm

`app/Mail/*.php`, `app/Core/Listeners/*.php`

All 10 `ShouldQueue` implementations define `$tries` and `$backoff` as **null**.
With `QUEUE_CONNECTION=database` and a default worker `--tries=0`, a persistent
SMTP or Expo Push failure → **infinite immediate retries with no backoff** →
retry storm that saturates the queue.

**Fix:** Set `public $tries = 3; public $backoff = [10, 60, 300];` on every
listener and mailable. Consider `public $maxExceptions = 3`.

## H10. NotificationController::markRead — no ownership check (auth bypass)

`app/Http/Controllers/Api/NotificationController.php:36-38`

```php
public function markRead(Request $request, Notification $notification): JsonResponse
{
    $notification->update(['read_at' => now()]);
    return response()->json(['ok' => true]);
}
```

No check that `$notification->user_id === $request->user()->id`. Any authenticated
user can mark any notification (any user's, any guest's) as read by guessing the ID.
`markAllRead` IS properly scoped to the authenticated user, so `markRead` was simply
missed.

**Fix:** Add `$notification->user_id === auth()->id()` gate.

---

# 🟡 MEDIUM Severity

## M1. N+1 on admin bookings list — user column lazy-loads per row

`app/Filament/Resources/Bookings/Tables/BookingsTable.php:38-46`

The `Customer` column uses `$record->user` inside a `getStateUsing` closure,
not a dot-notation column. Filament doesn't auto-eager-load it, so every row
with a registered user fires `SELECT * FROM users WHERE id=?`.

**Fix:** Override `getEloquentQuery()` to add `->with('user')`.

## M2. Missing database indexes

| Table | Missing | Impacted queries |
|---|---|---|
| `bookings` | `user_id` | `myBookings`, profile dashboard |
| `bookings` | `pickup_location_id`, `return_location_id` | All list views with location eager-load |
| `payments` | standalone `provider_reference` (only composite `gateway+provider_reference` exists) | Webhook lookup |
| `promo_codes` | `LOWER(code)` defeats unique index | Every checkout price preview |

**Files:** `database/migrations/2026_08_03_165335_create_bookings_table.php`,
`database/migrations/2026_08_03_185929_create_payments_table.php`,
`plugins/promotions/src/Filters/PromoCodePipe.php:48-50`

## M3. No global API throttle + critical endpoints unthrottled

- `POST /api/login` and `POST /api/register` — `routes/api.php:37-38`, **no throttle**.
  API login doesn't use the Breeze `LoginRequest` limiter (5/min per email+IP)
  that the web login uses.
- `POST /api/bookings/{booking}/confirm` — `routes/api.php:124`, **no throttle**,
  yet it does a synchronous Stripe API call per confirmation.
- No `$middleware->throttleApi()` in `bootstrap/app.php`. Only explicit per-route
  throttles exist.

## M4. Zero server-side caching — ~8-9 DB queries per page for singletons

`app/Http/Middleware/HandleInertiaRequests.php:72-191`

Every storefront page renders runs these uncached DB queries:
- `ThemeManager::resolveActive()` → `Theme::where('is_active', true)->first()`
- `latestDriverVerificationStatus()` → `DriverVerification::latest()->first()`
- `activeLayoutVariants()` → 1 query per registered slot (~6 queries)
- `siteIdentity()` → `SiteIdentity::first()`

These are admin-singleton data that changes rarely and is read on every request.
Ideal candidates for `Cache::remember()` with cache-bust on admin save.

Also: homepage runs featured query + count queries + locations + `HomepageContent::current()`
per request (`routes/web.php:25-49`). Theme tokens are re-resolved every request.
Nothing is cached server-side.

## M5. Unbounded endpoints — no pagination

- `Api\BookingController::myBookings` (`app/Http/Controllers/Api/BookingController.php:67-72`):
  `Booking::where('user_id', ...)->...->get()` — returns EVERY booking. No limit, no cursor.
- `Admin\BookingExportController::export` (`app/Http/Controllers/Admin/BookingExportController.php:31`):
  `$query->get()` loads the entire filtered set into memory before streaming.

## M6. No circuit breaker + 80s Stripe timeout on synchronous confirm

`docs/19-CIRCUIT-BREAKER.md` confirms: NOT IMPLEMENTED. The Stripe SDK default
timeout is 80 seconds. `BookingCheckoutController::confirm()` calls
`syncAuthorizationStatus()` synchronously — during a Stripe outage, every
confirm request blocks for up to 80 seconds with no fail-fast.

## M7. Double-queueing: ShouldQueue listener + ShouldQueue mailable

Listeners like `SendBookingConfirmationEmail` are `ShouldQueue`, and they call
`Mail::to()->send($mailable)` with a mailable that is also `ShouldQueue`.
Each email becomes two chained jobs: listener → mailable. Not a bug, but
adds hidden latency.

---

# 🟢 LOW Severity

## L1. Promo code LOWER() index bypass

`plugins/promotions/src/Filters/PromoCodePipe.php:48-50` —
`whereRaw('LOWER(code) = ?', ...)` on a column with a unique index.
The planner can't use the index. Create a functional index or a
`code_normalized` column.

## L2. UserPreferenceController lost-update race

`app/Http/Controllers/UserPreferenceController.php:30-34` — reads
`$user->metadata`, mutates one key, saves. Two concurrent saves can
lose updates. Low stakes (currency/lang prefs). Future: use
`$user->update(['metadata->key' => value])`.

## L3. BookingStatsOverview runs 5 queries where 1 would do

`app/Filament/Widgets/BookingStatsOverview.php:34-45` —
`realBookings()` is called 3 times (sum, count, avg) plus
`distinctCustomerCount()` does 2 more. Same status filter each time.

## L4. Booking number has no collision retry

`app/Models/Booking.php:29` — `strtoupper(Str::random(10))` in
the `creating` hook. No while-loop retry on unique constraint violation.
Extremely unlikely with 10 random chars, but no guard.

## L5. Health check reads wrong Stripe config key

`routes/web.php` checks `config('payments-stripe.secret_key')` but
the actual key is `secret` (`plugins/payments-stripe/config/payments-stripe.php:5`).
Stripe health always reports `missing`.

## L6. Post-commit queueing gap

`app/Providers/AppServiceProvider.php` — `after_commit` is false for all queue
connections. Jobs are dispatched inside DB transactions (e.g., `BookingConfirmed`
inside `BookingCreator`'s transaction). If the transaction rolls back after
dispatch, the queued job references a non-existent booking → `ModelNotFoundException`.

## L7. FilterRegistry/SlotRegistry static-state coherence under Octane

`app/Core/Support/FilterRegistry.php`, `app/Core/Support/SlotRegistry.php` —
static arrays, properly flushed in `PluginManager::boot()`. Fine for PHP-FPM.
Under Octane/persistent workers, an admin toggle on instance A is invisible to
instance B until restart. Not a current concern (no Octane adopted), documented.

## L8. No API resource transformers — DB columns leak

`app/Http/Controllers/Api/VehicleController.php`, `BookingController.php`, etc. —
Eloquent models serialized directly to JSON. Vehicle returns `license_plate`,
`metadata`, `photos`. Booking returns `guest_name/guest_email/guest_phone` (PII).
Own-data-only, but no field-level access control layer.

---

# What's done right (verified correct)

| Pattern | Status |
|---|---|
| BookingCreator `lockForUpdate()` + availability recheck | ✅ Correctly implemented |
| Availability-overlap composite index `(vehicle_id, status, pickup_at, return_at)` | ✅ Optimal |
| Pending-hold expiry index `(status, hold_expires_at)` | ✅ Optimal |
| `confirmPending()` idempotency guard (status !== 'pending' → return) | ✅ Correct |
| All list-rendering paths batch-load relations | ✅ CLAUDE.md rule 8 followed |
| FilterRegistry/SlotRegistry flush on every boot | ✅ Fix verified |
| Meilisearch → database `LIKE` fallback + 5s timeout | ✅ Good |
| PushNotificationService best-effort try/catch | ✅ Good |
| StripeGateway lazy client (no boot-time crash on missing key) | ✅ Good |
| CSRF exclusion for webhooks | ✅ Good |
| SecurityHeaders middleware | ✅ Good |
| CorrelationId middleware (web) | ✅ Good |

---

# Recommended fix order

1. **Config worker + scheduler** (H1) — unblock every async feature
2. **Payment authorization state machine** (H3) — close the double-action bug
3. **ReleaseExpiredBookingHolds race fix** (H2) — prevent data corruption
4. **Admin check-out/return atomicity** (H4) — prevent booking+vehicle divergence
5. **BookingCancellationService lock** (H5)
6. **StripeGateway retry + timeout** (H8) — money operations should retry
7. **Notification markRead ownership gate** (H10) — security
8. **Stripe webhook event-ID tracking** (H7)
9. **Email/push listener retry config** (H9) — prevent retry storms
10. **Global API throttle + unthrottled login** (M3)
11. **Singleton data caching** (M4) — cut ~8 queries per page
12. **Missing indexes** (M2)
13. **N+1 on admin bookings** (M1)
14. **Unbounded endpoints** (M5)

Generated: 2026-08-11
