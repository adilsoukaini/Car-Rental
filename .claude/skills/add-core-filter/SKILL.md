# Add a core-owned filter

Use this when a new named filter (`FilterRegistry::register()`/`apply()`)
involves a request/value object that more than one namespace needs to
construct or consume — not for a filter whose request type is only ever
touched by the single plugin that registers pipes on it (that's just a
normal part of `add-plugin`'s scope).

## 1. Decide where the request DTO lives — before writing it, not after

Ask one question: **will anything outside this one plugin need to
construct or consume this value object?** "Outside this one plugin" means
either of:

- **A second plugin** needs to construct or consume it, and the two
  plugins may never import each other directly (Hard Rule 2). Example:
  `App\Core\Support\DriverEligibilityCheckRequest` — `booking-engine`
  constructs it, `driver-verification`'s pipe consumes it. Placed in core
  from the start (Phase 9), correctly reasoned upfront.
- **A core class** needs to construct or consume it, and core may never
  import a plugin (Hard Rule 1). Example: `App\Core\Support\CancellationPolicyRequest` —
  `App\Filament\Resources\Bookings\Pages\ViewBooking` (core) needs to
  build one to preview a refund amount before cancelling, and
  `booking-engine`'s `CoreCancellationPolicyPipe` consumes it.

If the answer is "no, only the plugin that owns this filter ever touches
the request type," it can live in that plugin's own `Support` namespace
like any other internal class. If the answer is "yes," **the DTO goes in
`App\Core\Support` from the very first commit that creates it** — not
temporarily in a plugin "to be moved later if it turns out to matter."

**Why this matters, with the incident that proves it:** `CancellationPolicyRequest`
was originally written inside `Plugins\BookingEngine\Support` because at
the time it was created, only that plugin's own pipe touched it. A later
phase added `ViewBooking`'s cancellation-refund-preview logic in core,
which needed to construct the same request type — and did so by importing
it directly from the plugin namespace, a real, live Hard Rule 1 violation
that shipped and went unnoticed for one full phase before a pre-flight
check caught it (`grep -rln "use Plugins\\\\" app/`). The fix was
mechanical (move the file, update the namespace, update the ~3 call
sites) — but the violation itself, however briefly it existed, was a real
lapse in an architectural invariant this project otherwise holds strictly.
Deciding the DTO's home up front avoids the violation ever existing at
all, rather than relying on a later sweep to catch it.

## 2. Pick the result convention: short-circuit or transform-and-pass

Every filter in this project follows one of two conventions — pick
deliberately, don't default:

- **Short-circuit** (a yes/no gate): a pipe that finds a blocking reason
  returns `false` directly, without calling `$next()`. A pipe that finds
  no issue calls `$next($request)`, passing the same object through
  unchanged. The caller checks `$result !== false`, never plain
  truthiness. Used by `booking.availabilityCheck` and
  `booking.driverEligibilityCheck` — anything that's fundamentally a gate,
  not a computation.
- **Transform-and-pass**: every pipe fills in more of a mutable value
  object and always calls `$next($value)`. Used by `booking.priceCalculation`
  and `booking.cancellationPolicy` — anything that's fundamentally a
  computation building up a result.

Document which convention a new filter uses in `docs/event-registry.md`'s
"result convention" subsection for it — this has been a real point of
confusion-avoidance every time it's been done consistently, and a real gap
when a filter shipped without one (see the 2026-08-05 doc-completeness
fixes to `vehicle.listQuery`/`vehicle.reviews`, which were listed in the
filters table but never got this treatment).

## 3. Priority ordering, if more than one pipe can register on the same filter

`FilterRegistry::register($filterName, $pipeClass, $priority = 10)` — lower
runs first. If a later pipe reads a value an earlier one sets (e.g.
`CoreDepositPipe` reading `CoreDurationDiscountPipe`'s computed subtotal),
say so explicitly in a comment at the registration call site, not just in
the pipe's own docblock — `BookingEngineServiceProvider::boot()`'s
registration block does this today; keep doing it.

## 4. Document it in `docs/event-registry.md` in the same commit

Add a row to the "Named Filters" table, and a "### `filter.name` — result
convention" subsection (copy the shape of an existing one) explaining: the
convention picked, what the request/value object carries, what each
registered pipe actually does, and any real edge case a future reader
would otherwise have to re-derive from the code. This is the same
discipline Hard Rule 5 already states for Events/Slots — filters are no
different.

## 5. Verification checklist

- [ ] The request DTO's namespace matches the decision from step 1 — if
      anything outside the owning plugin touches it, it's in
      `App\Core\Support`, not a plugin namespace
- [ ] `grep -rln "use Plugins\\\\" app/` still returns nothing new (Hard
      Rule 1) after adding this filter
- [ ] No plugin imports another plugin's namespace to construct/consume
      this request type (Hard Rule 2)
- [ ] The result convention (short-circuit vs. transform-and-pass) is
      documented, not left implicit
- [ ] `docs/event-registry.md` has both the table row and the dedicated
      subsection
