# Kickoff Prompt — Car Rental Platform (New Project, New Chat)

Start a fresh project folder. Put `01-SYSTEM-DESIGN.md`, `02-DESIGN-SYSTEM.md`
(reuse the exact file from the e-commerce project — it's fully domain-agnostic,
just colors/fonts/spacing tokens), `03-DOMAIN-REQUIREMENTS.md`, and
`PROCESS-GUIDE.md` in a `/docs` folder, then paste this in.

---

## The prompt

```
I'm building a custom car rental platform — Laravel + Inertia.js + React, same
architectural philosophy as a previous e-commerce platform I built: a small,
stable core kernel, and every feature (booking, fleet management, payments,
locations, driver verification, etc.) built as an independent plugin
registering into the core via Events/Listeners (actions) and Pipeline
(filters), so features can be added, removed, or swapped without touching core
code. The frontend must be fully re-themeable from a single token file per
client (colors, fonts, spacing, radius), using the exact same three-tier token
system (primitives → semantic → component tokens) as before.

Read these docs fully before writing any code, in this order:
- docs/01-SYSTEM-DESIGN.md (the architecture — hooks, registries, plugins)
- docs/02-DESIGN-SYSTEM.md (the theme token system — reused unchanged from a
  prior project, it's fully domain-agnostic)
- docs/03-DOMAIN-REQUIREMENTS.md (what a car rental platform actually needs —
  read this carefully, the domain is genuinely different from e-commerce:
  availability is date-range based, not stock-based, and pricing/booking logic
  is the core complexity here, not cart/checkout)
- docs/PROCESS-GUIDE.md (the working discipline — how we build and verify
  each phase; follow this exactly, it's not optional)

CLAUDE.md is provided separately — read it too, it has the hard rules
(core/plugin boundary, code quality gates) that apply to every phase.

Do this in order, confirming with me before moving to the next phase. This is
a fresh project — build it exactly as carefully and incrementally as
docs/PROCESS-GUIDE.md describes. Do not rush multiple phases into one
response.

PHASE 1 — Project setup
- Laravel + Breeze + Inertia + React starter kit
- Folder structure exactly as docs/01-SYSTEM-DESIGN.md section on structure
  specifies
- Core Eloquent models + migrations: User, Vehicle, Booking, Location (see
  docs/03-DOMAIN-REQUIREMENTS.md for the exact fields each needs)
- Ask before running any migration

PHASE 2 — Kernel
- FilterRegistry (Pipeline-based), SlotRegistry, PluginManager — identical
  mechanisms to the e-commerce project, just fresh code in a fresh project
- Define and document core Events in docs/event-registry.md (BookingCreated,
  BookingConfirmed, BookingCancelled, VehicleReturned, etc. — see domain
  requirements doc for the full list)

PHASE 3 — Theme engine
- Port the token system from docs/02-DESIGN-SYSTEM.md exactly as before
- Build a throwaway test page confirming theme swapping works before moving on

PHASE 4 — First plugin: fleet/vehicle catalog
- This is the equivalent of "catalog" in the e-commerce build — prove the
  plugin pattern end to end with the first real, working feature before
  building anything else

Stop after each phase and show me it actually working, with real evidence —
per docs/PROCESS-GUIDE.md's standard, not a description of what should work.

Also create a CLAUDE.md at the project root (I'll provide a starting version
separately) and a SKILL.md for adding a new plugin, matching the discipline
used in the prior project.
```
