# Process Guide — How This Project Gets Built

This is the working discipline that made a prior 26+ phase e-commerce build
actually hold together without accumulating bugs or drift. Follow this exactly
— it is not optional flavor text, it's what keeps an AI-assisted build from
quietly degrading over time.

---

## 1. Every phase gets a design doc before code

Before building any feature, write (or receive) a short design doc: what's
being built, what decisions need explicit confirmation, what the build order
is, and what "verified" looks like. Claude Code should read the doc fully and
give a **pre-flight response** — flagging anything it disagrees with,
anything ambiguous, or any Hard Rule violation the doc itself might contain —
**before writing a single line of code.**

## 2. Confirm decisions explicitly — don't let Claude Code silently pick

When a doc presents a real judgment call (not a trivial detail), it should
state a recommended default and wait for explicit confirmation, not assume
its own preference is correct. The person directing the build should answer
these directly before work proceeds.

## 3. Every migration gets shown before running

No database change runs without the exact SQL/schema being shown first, and
explicit approval given. This applies to every migration, every phase, no
exceptions — it's the cheapest possible insurance against real data loss.

## 4. Verification means evidence, not description

"It works" / "all checks passed" is not verification. Every phase closes with
**actual evidence**: real query results shown side-by-side with expected
values, real screenshots, real before/after database state, actual error
messages when testing a failure case. If a verification step asks for 7
things, get all 7 — a summary that only covers 3 of them and calls it done is
incomplete, not finished.

## 5. Hold financial/security-adjacent code to a higher bar than everything else

Anything touching money (pricing, payments, deposits) or security (access
control, guest identity, IDOR-prone lookups) gets independently checked
against a manual calculation or a real attack attempt — not trusted because
the code "looks right." A wrong number on a pricing/revenue screen, or a
security gap that lets one customer see another's data, is categorically
worse than almost any other kind of bug.

## 6. Don't build extension points before they're needed

A registry, a swappable layout variant, a new abstraction — these earn their
complexity only once there's a genuine second real use case for them. Two
payment gateways justify a registry. One header design does not justify
making it swappable. When in doubt, build the plain version first, and only
generalize once a second concrete need actually shows up.

## 7. Watch for N+1 queries on any page rendering multiple items

Any time a listing/grid page needs per-item data from a different table
(images, prices, wishlist status, availability, whatever), that data must be
batch-loaded in one query for the whole page, never queried once per item.
This has been the single most repeated bug class across the prior build —
name it explicitly in any relevant design doc so it gets built right the
first time instead of needing a follow-up fix.

## 8. Respect the core/plugin boundary religiously

Core code (kernel, core controllers, core models) never imports a class from
a plugin. Plugins never import another plugin's classes directly. Cross this
boundary only through: Events, the Pipeline filter system, a Slot, a
container binding (for optional data one plugin might expose to another), or
a plain DTO carrying only primitive/array data. If a design doc's own example
code violates this, that's a bug in the doc worth catching before building it
— it has happened before and will happen again; catching it is expected, not
exceptional.

## 9. Code quality gates run every phase, not just at the end

Pint (style), a static analyzer (PHPStan/Larastan), and strict TypeScript
checking (`tsc --noEmit --strict`, zero `any`) all run and must pass before
any step is considered done. State explicitly that they passed — don't let
this become an assumed background fact nobody actually checks.

## 10. Write the skill/doc update AFTER the feature is proven, not before

Once a real pattern (a new plugin type, a new kind of registry) is built and
verified working, write it up as a reusable skill file and a CLAUDE.md
section — capturing the REAL gotchas that came up during the actual build,
not the plan as originally imagined. A skill written from a plan looks
correct on paper; a skill written after real code exists captures what
actually went sideways.

## 11. When something is found broken outside the current phase's scope, flag it — don't silently fix or silently ignore it

If reviewing one thing surfaces an unrelated gap (a missing navigation link,
dead code, a stale doc section), name it explicitly and let the person
directing the build decide whether to fix it now or defer it deliberately.
Silently expanding scope and silently ignoring a found problem are both
wrong — the right move is always to surface it.

## 12. Periodic audits catch what phase-by-phase verification can't

Even with every phase individually verified, some classes of bug only show up
when you deliberately step back and check something broadly: does every page
actually follow the design system? Does every important feature have a real
UI, or does something exist only reachable by typing a URL? Is CLAUDE.md
actually up to date, or did several phases promise doc updates that never
landed? Run these kinds of sweeps periodically — they have real, repeated
value, not just a one-time cleanup.

## 13. Check for "modeled but never consumed" at the start of every phase

A column, a model, or a method existing is not evidence that anything reads
or calls it. This project has caught the same shape of bug three times
independently: `Location.is_active` sat on the schema since Phase 1 with zero
code checking it; a `LocationResource` never existed despite `Location` being
a fully-modeled entity; `Payment::captureDeposit()`/`releaseDeposit()`/
`refund()` had no real caller anywhere outside tests. Each looked complete
from the outside (schema exists, logic is tested) and wasn't.

Before writing any code for a new phase, grep the existing schema/models/
methods for anything that plausibly belongs to the feature about to be built
— a column, an enum, a relation, a gateway method — and explicitly report
what's found, even if the answer is "nothing exists yet, genuine blank
slate." Don't assume a clean slate; confirm it. And before closing a phase,
ask the inverse question of anything new it added: is there a real caller/
consumer for this, or did it just get built and tested in isolation? A
staff-facing admin action, a webhook handler, an event listener — something
concrete has to actually invoke new financial or state-changing code before
the phase counts as done, not just a passing test suite around it.
