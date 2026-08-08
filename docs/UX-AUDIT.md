# UX Audit — Car Rental Storefront

**Date:** 2026-08-08
**Auditor:** UX specialist (research only — no code modified)
**Target:** Running app at `http://localhost:8099`, current `feat/integration` branch
**Method:** Playwright MCP — full-page and viewport screenshots, accessibility snapshots, console-log inspection, and real user-flow walkthroughs (guest + registered) across every customer-facing page. Screenshots referenced below were captured to the repo root as `audit-*.png`.

**Scope note:** This audit covers the customer-facing storefront and auth/profile pages. The Filament admin panel was not re-audited here (separate surface, covered by prior phases). Findings are limited to what a visitor/customer actually experiences.

---

## Severity key

- **P0** — Blocks a core flow or is a data/trust violation (fix before launch).
- **P1** — Annoying or misleading; materially degrades trust, comprehension, or a primary task.
- **P2** — Polish / consistency / i18n / accessibility; non-blocking.

---

## Executive summary

The app has a solid, theme-consistent storefront shell (header, footer, fleet grid, homepage, checkout, cookie banner, 404 page are all well-executed and token-driven). The dominant cross-cutting problem is **incomplete internationalization**: the storefront is French, but several entire pages and many inline strings are hardcoded English, and the language switcher does not touch them. The second theme is **trust and accuracy**: the cancellation "free up to 48h" claim contradicts the real refund policy, the driver-verification error leaks an internal user ID, and homepage stats claim +100 vehicles when only 20 exist. The third is a **handful of genuinely confusing moments in the paid flow** (payment-page back button goes to the homepage; the hold-expiry message reads as if the reservation ends minutes after creation; the confirmation page gives the customer no next step).

No P0 (blocking) defects were found — every page loads, the booking/checkout/payment flow completes, and there are zero console errors on the storefront pages (the only console messages are Stripe's staging-mode warnings). The priorities below are P1 items that should be addressed before a real launch.

---

## Cross-cutting findings

### C1. Incomplete i18n — French storefront with large English surfaces (P1)

**Current behavior.** The storefront default is French, and the header FR|EN switcher works for tokenized components. But the following are **hardcoded English and ignore the switcher**:

- **Entire pages:** Booking confirmation (`Bookings/Show.tsx`), Payment (`Bookings/Payment.tsx`), the whole Reviews section on vehicle detail (`VehicleReviewsCardList.tsx`), and all Breeze auth/profile pages (`/login`, `/register`, `/forgot-password`, `/profile`).
- **Inline strings inside otherwise-French pages:** breadcrumb `Home`; fleet sort options `Price: Low to High` / `Name: A–Z`; pagination `« Previous` / `Next »`; homepage stat `Best prices`; `You might also like`; the checkout mobile bar's `Pay now`.
- **Mixed within one page:** `/login` is English except the bottom French line "Pas encore de compte ? / S'inscrire"; `/profile` is English except the (French) driver-verification card; the checkout stepper is hardcoded French (`Véhicule` / `Paiement`) even when the page is switched to English.

**Expected.** One language applies consistently across a page (and ideally across the whole session), including pages reached mid-flow (checkout → payment).

**Implementation.** Route every hardcoded string through the existing `useTranslation()` hook / translation files (the mechanism already exists and works — it's just not applied). Minimum viable scope: (1) convert `Show.tsx`, `Payment.tsx`, `VehicleReviewsCardList.tsx`; (2) move the Breadcrumb/sort/pagination/stat strings into translations; (3) make the checkout stepper locale-aware. Auth/profile pages are covered separately in C3.

---

### C2. "Free cancellation (up to 48h)" contradicts the actual refund policy (P1)

**Current behavior.** Every vehicle detail page's "Inclus dans le prix" box promises **"Annulation gratuite (jusqu'à 48h)"** (`INCLUDED_FEATURES` in `Vehicles/Show.tsx`). The actual policy (`cancellation_refund_tiers` in `booking-engine.php`) is: ≥7 days → 100% refund; **2–6 days → 50% refund**; <2 days → 0%. So cancelling at exactly 48h (2 days) forfeits **half** the deposit, not "gratuite".

**Expected.** Marketing copy must match the enforcement logic, or the policy must be updated. Two options: (a) change the copy to "Annulation gratuite jusqu'à 7 jours" (matches the 100% tier) and keep the 2-day tier as the stated paid-cancellation threshold; or (b) change the refund tiers so 48h really is free.

**Implementation.** Either edit the copy constant in `Vehicles/Show.tsx` + the confirmation email template, or the config tiers. Also verify the cancellation modal on the admin side states the same numbers. This is a trust/legal exposure if a customer relies on the promise.

---

### C3. Auth + profile pages are the unthemed Breeze layout, in English (P1)

**Current behavior.** `/login`, `/register`, `/forgot-password`, and `/profile` use the stock Breeze guest/authenticated layouts: hardcoded indigo/gray Tailwind classes, no theme tokens, no storefront header/footer, no language switcher. The result is a visible jarring switch from the branded navy storefront into a generic Laravel shell. (`/profile` even retains a "Dashboard" link that just redirects to `/vehicles`.)

**Expected.** Auth pages carry the same branding/header as the storefront (or at least the same color system and a language switcher), and are localized.

**Implementation.** This is the deferred theming sweep already flagged in `CLAUDE.md` (`NavLink`, `Dropdown`, `ResponsiveNavLink`, the Profile partials). Wrap the auth pages in the themed `PublicLayout` (or a minimal themed auth layout), retokenize the shared Breeze components, and run all auth/profile strings through translations. Also either point the "Dashboard" link at a real destination or remove it.

---

### C4. Driver-verification error leaks an internal user ID and is English (P1)

**Current behavior.** A registered, unverified user attempting checkout sees the validation error **"User #12 is not eligible to book a 'economy' category vehicle."** (seen live in the checkout alert). It exposes the internal `User::id` to the customer and is in English inside a French page. (Screenshot: `audit-11-checkout-dv-error.png`.)

**Expected.** A customer-facing message like "Votre permis de conduire doit être vérifié pour réserver cette catégorie. [Vérifier votre permis]" — no internal identifiers.

**Implementation.** The eligibility pipe (`CoreDriverEligibilityCheckPipe`) / `BookingCheckoutController` currently returns a raw English string. Return a stable error *key* (e.g. `driver_verification_required`) and map it to a localized, non-identifying message in `CheckoutForm.tsx` (which already has a dedicated French alert block with a CTA).

---

### C5. Price formatting is inconsistent across surfaces (P2)

**Current behavior.** Fleet card: `250.00 DH / jour` (2 decimals). Vehicle detail: `350 DH / jour` (0 decimals). Recommendations: `350 DH / jour`. Checkout summary: `420 DH`. Homepage featured card: `1378.40 DH / jour`. The same vehicle reads differently in different places.

**Expected.** One canonical price format (recommend: `250 DH / jour` — trim trailing `.00`, matching the Stitch reference used on the detail page).

**Implementation.** Centralize a `formatPrice()` helper and use it in `VehicleGrid`/`Vertical` card, `VehicleRecommendations`, `Show`, and `CheckoutSummary`. Note the fleet page deliberately shows `.00` today; align all to the trimmed form.

---

### C6. No loading skeletons or wired error boundary (P2)

**Current behavior.** `LayoutSlot`/`SlotOutlet` lazy loads variants with `Suspense fallback={null}` — during a code-split load the region is blank, then pops in. A `Skeleton.tsx` component exists but is **never used**. An `ErrorBoundary.tsx` exists but is **not mounted** in `app.tsx` — an uncaught render error would white-screen the whole page with no recovery UI.

**Expected.** Skeleton placeholders in the fleet grid / homepage featured section during lazy loads; a mounted error boundary that shows a recoverable message instead of a blank page.

**Implementation.** Wire `ErrorBoundary` around `<App>` in `app.tsx`; add `Skeleton`-based placeholders to the `Suspense` fallbacks of `VehicleGrid`/`VehicleCarousel`.

---

## By page

### 1. Homepage (`/`)

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| H1 | P1 | **Stats are not believable** | "+100 Véhicules disponibles" but only 20 available vehicles exist; "+5000 Clients satisfaits" is unverifiable. | Accurate/defensible numbers, or generic claims without hard counts. | Either compute from real data (fleet count, booking count) or soften the copy. A customer counting ~20 cars on `/vehicles` and reading "+100 disponibles" loses trust. |
| H2 | P2 | **CTA band duplicates the hero** | "Prêt pour l'aventure ?" section is the same navy `bg-primary` as the hero, and its two buttons ("Découvrir nos véhicules" + "Réserver maintenant") both link to `/vehicles`. | Visually distinct band; primary CTA goes somewhere specific (scroll to search, or a highlighted category). | Give the band a different background (secondary/surface) and make the two buttons do different things, or keep one. |
| H3 | P2 | **"Best prices" stat untranslated** | Stat reads "Best prices / Garantie" — English inside a French page. | "Meilleurs prix" | Add the missing translation (the other three stats translate correctly). |
| H4 | P2 | **Search dates not pre-filled** | Hero search date fields are empty; fleet page and vehicle detail pre-fill dates. | Default to today+1 / today+2 so the primary CTA has a sensible default. | Pre-seed `pickupDate`/`returnDate` state in `Home.tsx` (same defaults as `Vehicles/Show`). |
| H5 | P2 | **Featured card missing image** | "Toyota quam" (vehicle 31) has no images; its featured card shows a gray placeholder icon while the other 3 cards show photos. | Every featured card has a real photo, or a nicer "no image" treatment. | Upload a real image for vehicle 31 in admin; add an empty-state image treatment to the card. (Screenshot: `audit-01-homepage.png`.) |

**Positives:** Hero heading + trust copy are strong; search card is prominent and overlaps the hero cleanly; value-prop section is clear; featured grid is rule-8 (one query); all four featured links work.

---

### 2. Fleet listing (`/vehicles`)

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| F1 | P2 | **"Affichage de 20 véhicules" is ambiguous on page 2** | The label shows the *total* available count (20) on both pages; page 2 actually lists 7. | "20 véhicules" (total) or "Affichage de 7–20 sur 20", ideally with "Page X sur Y". | Make the label reflect the total + current range. |
| F2 | P2 | **No pickup-location field in the filter bar** | The homepage search has a location field; the fleet filter has a "Ville" dropdown but no airport/location and no way to search by the date bar combining location. | Consistent location entry across homepage and fleet. | Add a location input to the fleet filter bar (and pass it to availability filtering), or document the city dropdown as the equivalent. |
| F3 | P2 | **Sort options + breadcrumb + pagination in English** | `Price: Low to High`, `Name: A–Z`, `Home`, `« Previous` / `Next »`. | French. | Translate (see C1). |
| F4 | P2 | **Default sort order** | Default "Défaut" order is the DB default (roughly by id). | A sensible default like price ascending or "featured". | Choose and document a default ordering; the sort control itself works correctly (verified `?sort=price_asc` reorders the grid live). |

**Positives:** Filter bar labels are clear; the search box has an excellent autocomplete (debounced, keyboard-navigable, `listbox` semantics, clear button) — verified working with live suggestions; the empty state is genuinely helpful ("Aucun véhicule ne correspond à votre recherche" + "Effacer les filtres"); pagination is discoverable.

---

### 3. Vehicle detail (`/vehicles/{id}`)

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| V1 | P1 | **Mobile ordering: booking form appears before the vehicle title & price** | On mobile the DOM stacks left column first: image → "Inclus dans le prix" → "Réserver" form → *then* title, specs, and price. A user must scroll past the booking form to learn what the car is or what it costs. | Title, spec summary, and price immediately under the image on mobile. | Use CSS `order` utilities (e.g. put the right column's title/price first on mobile) or restructure the grid so identity/price precedes the form. (Screenshot: `audit-17-mobile-vehicle-detail.png`.) |
| V2 | P2 | **Two identical "Continuer la réservation" CTAs** | The left "Réserver" form button and the right price-card link do the exact same thing. | One primary CTA (the price card is the natural primary). | Keep the sticky price-card CTA; demote/remove the duplicate in the left column, or keep the left form's date inputs and drop its duplicate button. |
| V3 | P2 | **Reviews section entirely English + no pending-approval messaging** | "Reviews", "No reviews yet.", "Leave a review", "Submit review" are hardcoded English. Any logged-in user sees the "Leave a review" form even without a returned booking; nothing tells the customer their review is hidden until staff approval. | Localized section; form only for eligible (verified-rental) users with a note like "Votre avis sera publié après validation". | Translate the widget (C1); gate the form on eligibility; add a "pending approval" hint after submit. |
| V4 | P2 | **Recommendation cards are sparse + English heading** | "You might also like" is English; cards show only category/name/price — no transmission/seats/fuel, no "Réserver" affordance (unlike fleet cards). | Localized heading; cards show the same spec chips + CTA as fleet cards for consistency. | Reuse the `Vertical` vehicle card with the recommendation data. |
| V5 | P2 | **Gallery controls are numbered buttons, not arrows/dots** | 3 thumbnails labelled "Voir l'image 1/2/3" below the image (accessible and functional, but no prev/next arrows). | Arrow + dot affordances are conventional for a car gallery. | Consider adding prev/next arrows alongside the thumbnails; keep the accessible labels. |
| V6 | P2 | **Price format differs from fleet page** | "350 DH / jour" here vs "350.00 DH / jour" on the fleet card. | Consistent format. | See C5. |

**Positives:** "Inclus dans le prix" trust box and "Aucun paiement requis maintenant" signal are well presented; spec grid is scannable; dates pre-fill; missing-vehicle empty state is friendly; recommendation links carry the selected dates through.

---

### 4. Checkout (`/vehicles/{id}/book?…`)

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| K1 | P1 | **"Aucun paiement requis maintenant" vs immediate deposit hold** | The summary says "No payment required now" (nothing charged), but the very next page holds 84 DH on the card ("Security deposit hold (charged now)"). A customer may be surprised by the hold. | Explicitly separate the *rental total* (charged at pickup) from the *deposit hold* (charged now, refundable). | Reword the checkout trust line to "Aucun prélèvement du total maintenant — une caution de X DH est pré-autorisée sur votre carte." Also state the hold on the payment page clearly (it does, but checkout should set the expectation). |
| K2 | P2 | **Mobile CTA label differs from desktop** | Mobile fixed bar button: "Pay now"; desktop summary button: "Confirm and pay". | Same verb. | Align both to "Confirmer et payer". |
| K3 | P2 | **Promo code has no discoverable success/available list** | Error state is clear ("Ce code n'est pas valide ou est inactif.", field marked `aria-invalid`). No UX for the success state visible without a real code (none exist in the dev DB). | (Nice-to-have) show applied discount inline (already supported via `promoDiscount` line) and perhaps a hint where codes come from. | The mechanics are sound; verify a valid code renders the green "Promo code applied" + discounted total. |
| K4 | P2 | **Stepper labels hardcoded French** | "Véhicule / Paiement" stay French even when lang=en. | Locale-aware. | See C1. |

**Positives:** Guest form field order (Prénom → Nom → Email → Téléphone with `+212` prefix) is logical; the logged-in state shows the account email clearly; price breakdown is explicit (daily rate × days, discount, insurance included, deposit); the driver-eligibility alert has a clear recovery CTA; the unavailability state offers "Go back and choose different dates"; mobile fixed CTA bar works (verified — `audit-16-mobile-checkout-viewport.png`).

---

### 5. Payment (`/bookings/{id}/payment` — rendered after `store()`)

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| P1 | P1 | **Back button dumps the user on the homepage** | The CheckoutLayout back button defaults to `backHref='/'` on the payment page (the checkout passes `backHref` to the vehicle, but the payment render does not). Clicking back mid-payment leaves a pending booking + card hold with no way back to it. | Back returns to the vehicle detail (or to a "return to payment" state), not the homepage. | Pass `backHref={route('vehicles.show', vehicle.id)}` (and a sensible `backLabel`) when rendering `Bookings/Payment` from `BookingCheckoutController::store()`. |
| P2 | P1 | **Hold-expiry message is confusing** | "This vehicle is reserved for you until Sat, Aug 8, 2026, 02:47 AM" — the hold expires ~15 min after creation, far earlier than a pickup days away, with no explanation. A customer may think they've lost the reservation. | Explain what the time means and what happens next. | Reword to e.g. "Payment hold expires in ~15 min. If payment isn't completed, the vehicle is released and your dates become available again." Compute the countdown from `hold_expires_at` rather than a raw timestamp. |
| P3 | P2 | **Page entirely English** | "Complete your booking", "Vehicle", "Total price", "Pay security deposit hold". | French (or locale-aware). | See C1. |
| P4 | P2 | **Stripe staging warnings** | Console shows: HTTPS required for live; `link` payment method not activated; Apple Pay domain not registered/verified. | Zero warnings in production. | Deployment checklist: serve over HTTPS, activate `link`/card methods in the Stripe dashboard, register+verify the domain. Not a code defect — staging only. |

**Positives:** Stripe Elements integrates cleanly (card number, expiry, CVC, country defaulting to Morocco); the "Security deposit hold (charged now)" line is honest; the total-vs-hold split is clear on this page; the confirm flow works end-to-end (verified earlier phases). (Screenshot: `audit-12-payment.png`.)

---

### 6. Booking confirmation / tracking

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| B1 | P1 | **Confirmation page has no next step** | `Bookings/Show` is a bare summary (vehicle, pickup/return, total, deposit) with no guidance: nothing about what to bring, when to arrive, how to modify/cancel, or how to get help. A guest who just booked is left with "now what?". | A clear "What happens next" block (e.g. confirmation email sent, bring license at pickup, free cancellation window, contact support), plus an action (track / modify / contact). | Add a next-steps section and CTAs to the Show page. |
| B2 | P2 | **Confirmation page entirely English** | "Booking #…", "Vehicle", "Pickup", "Return", "Total price", status pill. | French. | See C1. |
| B3 | P2 | **Track lookup has a rare silent-failure edge** | Normally the lookup redirects back with a clear French error ("Aucune réservation trouvée avec ces informations."). One reproduction hit an Inertia 409 (asset-version conflict) and silently redirected to `/` instead of showing the error. | Never silently redirect; always show the error or stay on the form. | Investigate the 409 path (likely dev asset recompilation); ensure Inertia handles the redirect-with-errors response without a full-page fallback to `/`. |

**Positives:** The track page is clean, French, and the not-found error is clear when it works; signed-URL guest access is correct.

---

### 7. Auth pages (`/login`, `/register`, `/forgot-password`)

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| A1 | P1 | **Unthemed + English + no language switcher** | Stock Breeze guest layout (see C3). Login mixes English fields with a French bottom link. | Branded, localized. | See C3. |
| A2 | P2 | **Forgot-password email field has no label** | The email input on `/forgot-password` is a bare textbox with no visible/`aria` label. | Labelled input. | Add `<label>` / `aria-label="Email"` and `type="email"`. |
| A3 | P2 | **Register page fully English** | "Name", "Email", "Password", "Already registered?". | French. | See C1/C3. |

---

### 8. Profile (`/profile`) & driver verification

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| R1 | P1 | **Profile page is the unthemed Breeze layout, English** | See C3. The booking-history widget ("Your last 0 bookings.") and account-management sections are English; the driver-verification card is French. No language switcher. | Themed + consistent language. | See C3. |
| R2 | P2 | **"Dashboard" link is a dead stub** | The profile nav's "Dashboard" link hits `/dashboard`, which just redirects to `/vehicles`. | A real destination or no link. | Remove the stub or point it at booking history. |
| R3 | P2 | **Verification banner links to /profile, not the form** | The "Complétez votre profil — ajoutez votre permis…" banner (shown on every page to an unverified user) links to `/profile`; the user then clicks "Vérifier" to reach the form. | Link directly to the driver-verification form. | Change the banner + checkout alert links to `route('driver-verification.show')`. |
| R4 | P2 | **Country dropdown uses English country names** | Driver-verification "Pays du permis" lists "Morocco", "France", … in a French form. | French country names (or the app's locale). | Provide localized country labels. |

**Positives:** The verification status card handles all four states well (none/pending/approved/rejected with reason + resubmit); the pending state copy is reassuring.

---

### 9. Global elements

| # | Sev | Finding | Current | Expected | Fix |
|---|-----|---------|---------|----------|-----|
| G1 | P2 | **Header nav omits a direct Driver Verification link for logged-in users** | For a logged-in, unverified user the header shows "Mon Compte" / "Se déconnecter" but no direct verification entry; reachability relies on the banner (which links to /profile). | A direct link when verification is missing/pending/rejected. | Add a conditional header link (the mechanism is documented in `CLAUDE.md`; current deployed bundle doesn't show it — see C3 note). |
| G2 | P2 | **Cookie banner** | Works correctly — fixed bottom, localStorage-dismissible, translated, appears on 404 too. | — | No change. Verified after clearing localStorage. |
| G3 | P2 | **Header/footer consistency** | Header and footer are identical and correct across all storefront pages (verified on every page). Footer account section adapts to auth state. | — | No change. |
| G4 | P2 | **Mobile hamburger menu** | Works correctly (verified): opens a stacked nav with all links + language switcher. | — | No change. |
| G5 | P2 | **Loading state** | Inertia top progress bar exists; no skeleton placeholders (see C6). | — | See C6. |

---

## Top 10 prioritized improvements

1. **Fix the payment-page back button** (P1, P1) — pass `backHref` to the vehicle detail so a user mid-payment isn't orphaned on the homepage with a pending hold.
2. **Make the "Free cancellation (up to 48h)" claim honest** (P1, C2) — align copy with the real 7-day/2-day refund tiers (or the tiers with the copy).
3. **Stop leaking internal user IDs in the driver-eligibility error** (P1, C4) — localized, non-identifying message.
4. **Clarify "no payment required now" vs the immediate deposit hold** (P1, K1) — set the deposit-hold expectation before the payment page.
5. **Explain the hold-expiry on the payment page** (P1, P2) — countdown + plain-language consequence instead of a raw timestamp.
6. **Add a "next steps" section to the booking confirmation page** (P1, B1).
7. **Complete the i18n pass on the paid flow** (P1, C1) — Payment, Booking Show, Reviews, checkout mobile CTA, breadcrumb/sort/pagination, and make the stepper locale-aware. This is the largest visible polish win.
8. **Theme + localize the auth and profile pages** (P1, C3) — unify branding and language; fix the forgotten-password unlabeled email field.
9. **Fix mobile vehicle-detail ordering** (P1, V1) — show title, specs, and price before the booking form.
10. **Make the homepage stats real** (P1, H1) and add skeleton/error-boundary robustness (P2, C6).

**Suggested quick wins (do first, low risk):** translate the breadcrumb/sort/pagination strings; align price formatting (C5); point the profile "Dashboard" link elsewhere or remove it; link the verification banner directly to the form (R3); label the forgot-password email field (A2).
