# Competitive UX Analysis — Car Rental Platform

**Date:** 2026-08-08
**Method:** Live extraction of DiscoverCars.com, Rentalcars.com, Kayak.com/cars, and Expedia.com/Cars (Expedia is Akamai-blocked from this sandbox, so its findings come from Expedia's published product/API docs + usability research; the other three were captured directly). Our site audited end-to-end in a real browser at `http://localhost:8099` — homepage → fleet → vehicle detail → checkout → Stripe payment → confirmation, plus profile, driver verification, admin panel, booking-track, auth pages, and a 390px mobile pass. Zero app console errors throughout (the only console messages are Stripe.js test-mode/HTTP warnings).

---

## 1. Competitor patterns — what the top sites all do

### 1.1 Search form (the most important element)

All four lead with a **search box as the hero**, and it captures far more than dates:

| Field | DiscoverCars | Rentalcars | Kayak | Expedia | Ours |
|---|---|---|---|---|---|
| Pickup location | ✅ (autocomplete) | ✅ | ✅ | ✅ | ✅ combobox |
| "Return same location" toggle / different-drop-off | ✅ checkbox | ✅ (same location) | ✅ "Same drop-off" toggle | ✅ | ❌ (one-way exists server-side, not in search UI) |
| Pickup date | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Pickup time (30-min steps)** | ✅ 00:30…23:30 | ✅ | ✅ "Noon" | ✅ | ❌ **dates only** |
| Drop-off date | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Drop-off time** | ✅ | ✅ | ✅ | ✅ | ❌ **dates only** |
| **Driver country of residence** | ✅ defaulted to Morocco | ❌ | ❌ | ✅ (implied) | ❌ |
| **Driver age** | ✅ 18–80+ ("30-65" default) | ❌ | ❌ (FAQ only) | ✅ (under-25/over-70 fees) | ❌ (eligibility exists but not asked up front) |
| Flight-number search | ✅ "Arriving by plane? Search by flight" | ❌ | ❌ | ❌ | ❌ |

- **Time matters in car rental**: pick-up/drop-off times drive availability, price, and the deposit-hold window. Every competitor asks for them in 30-minute increments.
- **Driver age + country are asked up front** because they change both *which cars you can rent* and *price* (young-driver fees). DiscoverCars says it explicitly: *"If you enter your age before searching, we include this fee in the total."* We already *compute* age eligibility per category — we just never ask for the inputs.

### 1.2 Price display conventions

- **Suffix is consistent and always present:** DiscoverCars uses `from MAD 145.56 per day` on location pages and `MAD 282.94` in results; Kayak uses `$28+` everywhere; Rentalcars shows per-day totals in the search box context.
- **"from X" phrasing** appears on every SEO/location page (`Casablanca Airport from MAD 185.04 per day`). This is the universal "we have cheap options" signal.
- **All-inclusive framing is a core pitch.** DiscoverCars headline: *"Clear prices, no surprises"* and *"we include all mandatory fees, taxes, and extras in the quoted price"*. This is a direct answer to the industry's #1 complaint (hidden fees at the counter).
- **Deposit is always disclosed** — DiscoverCars has a dedicated *"Keep the Deposit in Mind"* education block and shows deposit amounts in rental conditions.

### 1.3 Trust signals (everywhere, not just the footer)

DiscoverCars is the extreme example, but the pattern is shared:
- **Review scores as a hard number with volume**: Trustpilot `4.6 / 5 · 279,894 reviews`, Google `4.5 · 55,000+`, App Store ratings. Kayak: `1M+ ratings on our app`. Rentalcars relies on the Booking.com brand.
- **Volume/size proof**: DiscoverCars *"Trusted by 7M travelers"*, *"50K+ locations · 164 countries · 1,000+ partners · 35 languages"*; Kayak *"41,000,000+ searches this week"*.
- **Badges & awards**: DiscoverCars shows World Travel Tech Awards 2025, Magellan Gold, FT 1000, plus PCI-DSS and PositiveSSL in the footer.
- **Live review carousel**: named reviews with dates ("Roy, Aug 8, 2026").
- **Guarantee pages**: DiscoverCars has a dedicated `/price-guaranteed` page ("Our Guarantee") linked from the header/footer.
- **"Free cancellation"** is stated in the hero trust bar (DiscoverCars) and in every FAQ.

### 1.4 Vehicle detail / results cards

- **Cards** (Kayak/Expedia results): image, car-class label, name, per-day price, supplier/company name, **review score badge per car** (DiscoverCars shows supplier ratings + "Excellent Car Rental Service" badges for 8.0+ suppliers), and a prominent CTA.
- **Detail page**: multi-image gallery (thumbnails), spec list, **"Included in price" / policies section**, insurance info, cancellation policy under "Policies", price + deposit summary in a sticky booking sidebar.
- **Results toolbar**: filters for car type/company/pick-up location/payment option (Expedia adds a **map view**; Kayak has a map), plus sort by price.

### 1.5 Checkout & upsells

- **Upsell step is standard**: Expedia has a dedicated *"Select extras"* step (insurance products, child seats, GPS, additional driver) surfaced from the car-details API; the under-25/over-70 insurance surcharge is flagged there. Kayak has a *car rental insurance guide*.
- **Cancellation policy is surfaced at multiple points**: on the results card, on the detail page under "Policies", and again at checkout. Expedia: *most rentals 100% refundable if canceled ≥24h before pickup*.
- **Member pricing**: Expedia shows member prices and prompts *"Sign in to unlock Member Prices"*; DiscoverCars has a login modal pitching *"Member deals — Save 30% · Free additional driver"*.
- **Login via social**: DiscoverCars offers Continue with Google / Apple / email.

### 1.6 Post-booking

- **"Manage booking" is in the header on every page** (DiscoverCars, Rentalcars both have a persistent "Manage booking" entry — guests don't have to remember a URL).
- **Confirmation = clear next steps**: booking reference, what was paid, deposit amount, pickup time/location, and "add to calendar" or "manage" actions.
- **FAQ answers "what do I need at pickup"** everywhere (license held 12–24 months, credit card for deposit, passport, voucher).

### 1.7 Mobile

- Search form collapses to stacked fields; time pickers remain.
- Sticky bottom CTA bars appear on checkout/results (we already do this — see strengths).
- Kayak/DiscoverCars push their **apps** heavily (QR scan, "see pick-up info on the map").

---

## 2. Our gaps — what we're missing compared to them

### GAP-1 · No pickup/drop-off **time** anywhere in the search or booking UI
We ask for dates only (homepage, fleet, vehicle detail, checkout). Competitors universally capture times in 30-minute steps. This is the single biggest functional gap: times drive availability, price display, and the hold expiry, and their absence makes the booking feel incomplete to anyone who has rented before.
**Source:** DiscoverCars, Rentalcars, Kayak, Expedia search forms.
**Fix:** Add a time dropdown (30-min steps, default 10:00/11:00 like DiscoverCars) next to each date, threaded through `pickup_at`/`return_at` everywhere those dates already flow.

### GAP-2 · Search never asks **driver age or country**
We already run a real driver-eligibility check per category at checkout (a strength!), but we never collect the inputs that would let us (a) filter the fleet up front, (b) show "young-driver fee" pricing, or (c) communicate the rule like competitors do.
**Source:** DiscoverCars (country + age in the search box, fee included in total); Expedia (under-25/over-70 surcharge); Rentalcars/Kayak FAQs.
**Fix:** Add "driver's country of residence" + "driver age" to the homepage search (collapsed by default), pass them to the fleet, and show an age-eligibility notice on category-gated vehicles.

### GAP-3 · No **currency selector**
Our prices are hard-locked to MAD. DiscoverCars (defaults to MAD for this region but offers 60+ currencies), Rentalcars, and Kayak all expose a currency switcher in the header. For an internationally-facing Moroccan rental business this is table stakes.
**Source:** DiscoverCars & Rentalcars header currency dropdowns.
**Fix:** A header currency dropdown (MAD default; USD/EUR/GBP + a few more), persisted via `?currency=` + a whitelist (mirror the existing `?lang=` whitelist pattern in `HandleInertiaRequests`).

### GAP-4 · **Reviews are invisible** until you open a detail page, and even there they're an empty state
Competitors show review scores on every card and on the detail page with volume ("279,894 reviews"). Our fleet cards show no rating; detail pages show "No reviews yet." (The review model + submission form work — they're just never surfaced.)
**Source:** DiscoverCars (per-supplier scores + Trustpilot), Kayak (`1M+ ratings`), Expedia member reviews.
**Fix:** Compute an average rating + count per vehicle (one query, rule 8) and render a star/score chip on each fleet card and the detail page; on the detail page, list real reviews above the write-review form. Consider a layout-variant region so it's swappable.

### GAP-5 · No **aggregate trust signals** on the homepage
We have a stats bar (`+20 véhicules · 17 lieux · 24/7 · Best prices`) but no review rating, no customer count, no badges, no guarantee link. Competitors lead the page with "Trusted by 7M travelers · 4.6/5 from 279,894 reviews · Free Cancellation".
**Source:** DiscoverCars hero trust bar, Kayak "searches this week", Expedia "Sign in to unlock Member Prices".
**Fix:** Add a hero trust strip (rating score + count, "24/7 assistance", "Free cancellation", "No hidden fees") wired to real data where possible (e.g., review average from the reviews table), and add a "How it works / what you need at pickup" block under the hero (license, ID, card, voucher) like DiscoverCars.

### GAP-6 · No **insurance/extras upsell step**
Competitors treat "Select extras" (insurance upgrades, child seats, GPS, additional driver) as a dedicated, revenue-generating step. We have "Assurance incluse" and a deposit hold, but nothing to add on.
**Source:** Expedia (extras step + under-25 insurance surcharge), Kayak (insurance guide), DiscoverCars (extras in rental conditions).
**Fix:** Add an optional extras step after vehicle selection (child seat, additional driver, GPS, full-cover upgrade) as a plugin registering on `booking.priceCalculation` (the discount-pipe pattern already exists for this). This is the highest revenue upside of any gap.

### GAP-7 · Vehicle detail **gallery is a single image**, no thumbnails
We have a `vehicle-media` plugin and multiple images per vehicle, but the detail page shows one photo. Competitors show 5–15 photos with thumbnails.
**Source:** DiscoverCars/Kayak/Expedia detail galleries.
**Fix:** Render the existing `vehicle-media` images as a thumbnail-strip + main image (the `vehicle-gallery` layout-variant region already exists — implement the default variant to use all images).

### GAP-8 · No **"Manage booking" in the header**
Guests must scroll to the footer "Suivre votre réservation". DiscoverCars and Rentalcars both have a persistent "Manage booking" entry in the header, since most car-rental visitors are mid-trip.
**Source:** DiscoverCars & Rentalcars headers.
**Fix:** Add a "Manage booking" / "Suivre votre réservation" link to `PublicLayout`'s header (next to the locale switcher), opening the existing track page.

### GAP-9 · **Auth is bare Breeze** — no social login, no member framing
DiscoverCars: "Continue with Google / Apple / email" and a "Member deals — Save 30% · Free additional driver" login modal. Expedia: "Sign in to unlock Member Prices." Ours is a plain email/password form.
**Source:** DiscoverCars login modal, Expedia member pricing.
**Fix:** Frame the login/register pages around what an account buys (booking history, faster checkout, driver-verification status, loyalty discounts — all of which already exist server-side) and add "Why create an account?" copy. Social login is a larger lift; the member-benefits copy is cheap and immediate.

### GAP-10 · Homepage copy carries a **placeholder brand name**
`HomepageContent::features_title` defaults to **"Pourquoi choisir Project Atlas ?"** and renders on the live homepage. "Project Atlas" was the scaffold name, not the client's.
**Source:** Our own live homepage (`http://localhost:8099/`) + `app/Models/HomepageContent.php:52`.
**Fix:** Change the seeded/default `features_title` (and any other residual "Project Atlas" strings) to the real brand copy. One-line data change; verify on the homepage.

### GAP-11 · No **FAQ / education section** on the homepage
Competitors dedicate a full homepage section to "What do I need to rent a car?", "At what age?", "Is insurance included?", "Can I cancel free?". This doubles as SEO and pre-empts counter surprises.
**Source:** DiscoverCars & Rentalcars & Kayak homepage FAQs.
**Fix:** Add a 4–5 item FAQ accordion (age, documents, deposit, cancellation, one-way) backed by the already-accurate domain policies.

### GAP-12 · No **map view** on results (Kayak/Expedia both have one)
Lower priority than the above, but it's a differentiator for a location-heavy product.
**Source:** Kayak & Expedia results maps.
**Fix:** Defer, or add a simple map toggle if a map provider is ever introduced. Do not build before GAP-1..GAP-5.

---

## 3. Our strengths — what we do better than the competitors

1. **Driver verification is genuinely unique.** Competitors treat documents as a counter-time check. We have a real profile-based flow: upload license (number, country, DOB, document) → admin review → status (`pending/approved/rejected`) → **enforced at checkout per category** (a non-verified account is stopped with a clear message and a "Vérifier mon permis" link). The full storefront→admin→storefront round-trip was verified working in this audit. This is a differentiator worth marketing ("contrat digital" is already on the homepage).

2. **Price transparency at checkout beats all four competitors.** Our checkout shows: line-item rate (`650 DH × 1 jour`), `Assurance incluse`, `Caution` (deposit), `Total` + `Taxes incluses`, a plain-language pre-auth note (*"Cette pré-autorisation n'est pas un débit — elle sera annulée automatiquement au retour"*), and the full cancellation schedule in-context. This is exactly the "no hidden fees" trust competitors advertise — we actually ship it.

3. **The deposit-hold flow is production-grade.** 15-minute hold-expiry countdown on the payment page ("Votre réservation expire dans 15 minutes"), real Stripe `PaymentIntent` with `capture_method: manual`, availability blocked while a hold is live, release-expired-holds scheduler. A real, verified Stripe test transaction completed end-to-end in this audit.

4. **Confirmation page is best-in-class.** Booking reference badge, status, vehicle/pickup/return/total/deposit, a **"Prochaines étapes" checklist** (✅ confirmed + ref · 📧 email sent · 📍 pickup time/place), and two clear CTAs. This matches or beats the aggregators, which tend to hand off to the supplier at this point.

5. **Guest booking access via signed URLs** + a **booking-track lookup** (reference + email → signed link). Guests get the same detail page as owners without an account — competitors require accounts or supplier hand-off.

6. **A real admin panel with control surfaces competitors don't expose:** booking calendar widget, theme system (zero-rebuild swaps), layout-variant registry, homepage content, site identity, promo codes, bulk vehicle import, plugin toggles, analytics widgets. This is a platform, not a brochure site.

7. **Server-side filtering/sorting + real search** with a shareable, refreshable URL (not just client-side filtering like many smaller sites).

8. **Mobile sticky pay bar** on checkout ("Total 500 DH / Payer maintenant") — a proven conversion pattern that Kayak/Expedia also use.

9. **Accessibility foundation** (skip links, ARIA, focus rings, contrast-validated palette, heading hierarchy) is genuinely ahead of the aggregators, which are ad-heavy and cluttered.

10. **i18n (FR/EN) with a whitelisted `?lang=` switcher** — the storefront default is French, which fits the Moroccan market better than any of the four (all of which default to English).

---

## 4. Priority improvements — ranked by UX impact vs. effort

| # | Change | Impact | Effort | Notes |
|---|---|---|---|---|
| 1 | **Add pickup/drop-off time pickers** (30-min steps) to homepage search, fleet, vehicle detail, checkout | High — completes the core rental mental model | Low | Thread through existing `pickup_at`/`return_at`; default 10:00/11:00 |
| 2 | **Fix "Project Atlas" copy** + scan for other scaffold names | High — brand credibility, trivially cheap | Trivial | `HomepageContent` default + grep the whole repo |
| 3 | **Surface review scores on fleet cards + detail** (star chip + count, real reviews listed) | High — builds trust, matches competitors' #1 signal | Medium | One aggregate query (rule 8) + a chip component |
| 4 | **Header "Manage booking" link** | High — guest lifecycle | Trivial | Add to `PublicLayout` next to locale switcher |
| 5 | **Homepage trust strip + "what you need at pickup" + FAQ** | High — conversion & SEO | Medium | Real-data rating where possible; copy from our real policies |
| 6 | **Currency selector** (MAD default, whitelisted `?currency=`) | Medium-High — international customers | Medium | Mirror the `?lang=` whitelist pattern |
| 7 | **Add driver age + country to search**, surface age-eligibility per vehicle | Medium — matches competitor search and our existing eligibility logic | Medium | Collapsed fields; pass to fleet |
| 8 | **Multi-image gallery with thumbnails** on vehicle detail | Medium | Low | Use existing `vehicle-media` images in the `vehicle-gallery` region |
| 9 | **Insurance/extras upsell step** (child seat, additional driver, GPS, cover upgrade) | High revenue upside, larger build | High | Plugin on `booking.priceCalculation`; own data model (rule 6) |
| 10 | **Auth framing** (member benefits copy, why-account) — social login later | Medium | Low | Breeze pages already exist; add value copy |
| 11 | **Map view on results** | Low-Medium | High | Defer until a map provider is decided |

---

## 5. Professional polish checklist — small details that make it feel premium

Every item below is a concrete, low-effort fix observed directly during the audit.

### Copy & language consistency
- [ ] **"Pourquoi choisir Project Atlas ?"** → real brand name (`HomepageContent` default + seed; grep `Project Atlas`/`Projet Atlas` across the repo).
- [ ] Homepage stats bar mixes English into French: **"Best prices"** → "Meilleur prix" (and confirm the other three items are the same voice).
- [ ] Profile page mixes languages: headings "Recent bookings", "Leave a review", "Your last 0 bookings", "Permis de conduire" section is French but "Recent bookings" is English. Pick one language per page (storefront locale).
- [ ] Vehicle detail page headings are partially English: "Reviews", "Leave a review", "No reviews yet", "Submit review" appear untranslated under a French page (the i18n keys exist — they're just not all mapped). Audit `lang/fr.json` coverage for the detail/profile pages.
- [ ] Payment page ("Complete your booking", "Total price", "Security deposit hold") is English while checkout is French — inconsistent. Translate the payment page for the storefront locale.
- [ ] Vehicle names like **"Toyota quam"** and cities like "Criststad", "Ornburgh", "Schultzside", "Port Macibury" are faker-generated data — for any demo/training or client demo, re-seed with real Moroccan fleet + city names.

### Price display
- [ ] Standardize the price suffix across surfaces: `650 DH / jour` (cards) vs `"500.00"` (checkout/payment) vs `1378 DH / jour`. Show currency consistently (e.g. `650,00 DH` or `650 DH`) and a `+`/`from` hint where appropriate.
- [ ] Show **per-day → total** on the fleet card when a date range is active (competitors show both "per day" and the range total).

### Cards & listing
- [ ] Add **vehicle year** to the card title line (`Renault Clio (2022)` is only on the detail page) — every competitor shows the model year.
- [ ] Show a **category/class chip** consistently (we do) and add the **rating chip** (GAP-4).
- [ ] Result count already present ("1–12 sur 20 véhicules") — good; keep it. Add the active-filter summary ("3 véhicules · SUV · Automatique") like Kayak.

### Vehicle detail
- [ ] Replace the single photo with the full **gallery** (GAP-7).
- [ ] Present specs (seats, transmission, fuel, year, category) as a small **spec table** rather than only badges.
- [ ] Empty review state: "No reviews yet" is fine functionally but add a prompt ("Soyez le premier à donner votre avis après votre location") to encourage the first review.

### Trust & reassurance
- [ ] Add a **"Secure payment" badge row** on the checkout (lock icon, "Paiement 100% sécurisé via Stripe") — the header already says "Paiement sécurisé"; make it visible and reassuring.
- [ ] Add **"Free cancellation" / "Annulation flexible"** to the vehicle cards as a micro-badge (matches DiscoverCars' hero trust bar and reduces booking hesitation).
- [ ] Add the **deposit amount** to the vehicle detail page (we show it at checkout; showing "Caution 130 DH" on the detail sidebar pre-empts surprise).

### Header / navigation
- [ ] Add **"Manage booking"** to the header (GAP-4 / strength #… above).
- [ ] Mobile menu is missing the **"Driver Verification"** entry that the desktop header shows when a user isn't verified — add it so the profile-completion nudge is reachable on mobile.

### Admin panel
- [ ] Investigate the recurring **419 / "This page has expired"** on Filament Livewire actions observed during this audit (CSRF token mismatch on `/livewire/update`). Likely environment/session-config (SESSION_DRIVER=database on `localhost`), but worth ruling out before launch — an admin who can't approve a driver verification is a support ticket.

### Accessibility & performance
- [ ] Add `aria-live` to the booking confirmation "Prochaines étapes" so screen readers announce the success (currently decorative ✅/📧 emoji).
- [ ] Confirm gallery images use the existing `loading="lazy"` pattern (the docs say they should; verify once the gallery renders multiple images).

---

## Source notes

- **DiscoverCars** (`https://www.discovercars.com`) — captured homepage HTML in this session: search form fields/times/age/country, `from MAD X per day` pricing, Trustpilot 4.6/279,894 + Google/App Store ratings, award badges, "Trusted by 7M travelers", "What do you need to pick up the car?", FAQ, member-deals login modal (Save 30%, Free additional driver), Google/Apple/email login.
- **Rentalcars** (`https://www.rentalcars.com`) — captured homepage HTML: MAD/language header, persistent "Manage booking", search (date+time), supplier logo row, Morocco city/airport SEO pages (Casablanca, Marrakech, Agadir, Tangier, Fes, Rabat), FAQ (docs, age 21–70, what's included, fuel policy), "How we work" page.
- **Kayak** (`https://www.kayak.com/cars`) — captured homepage HTML: "Find the right car from 100s of sites", Same drop-off toggle, "SUVs only" quick filter, `41,000,000+ searches this week`, `1M+ ratings`, `$28+` price list, extensive FAQ (age, one-way, debit, insurance guide, cross-border), "Different drop-off" for one-way.
- **Expedia** (`https://www.expedia.com/Cars`) — blocked by Akamai bot detection in this sandbox (HTTP 429). Findings sourced from Expedia's published product/API docs and usability research: results filters (car type, payment option, company, pickup location) + map view, member pricing ("Sign in to unlock Member Prices"), "Select extras" step with insurance upsell + under-25/over-70 surcharges, cancellation policy on detail page (100% refundable ≥24h before pickup), and known UX findings (interstitial auto-advance, discount-code placement).
- **Our site** — audited live at `http://localhost:8099` with a real browser: every page listed in the summary, a real confirmed booking completed through Stripe test mode, driver-verification approved via the admin panel, booking-track signed-URL lookup verified, and a 390px mobile pass.
