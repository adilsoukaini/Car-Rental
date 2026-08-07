# Homepage

> **Adapted from e-commerce homepage-design.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ✅ **Real storefront homepage at `/` replacing Laravel's Welcome scaffold** — DONE. `resources/js/Pages/Home.tsx` (hero, booking-card visual, value props, featured-vehicles grid, CTA banner, stats bar) wrapped in `PublicLayout`, served by the `/` route in `routes/web.php`.
> - ✅ **"New Releases"-equivalent section (featured vehicles)** — DONE. The homepage renders the 4 most recently added `available` vehicles as `featuredVehicles` (one query, rule 8) through the `vehicleCard` layout variant.
> - ❌ **Admin-editable hero/promo content** — NOT DONE. There is no `homepage_content` / `homepage_promo_blocks` table and no `HomepageContentSettings` Filament page — the hero headline/image and promo sections are hardcoded in `Home.tsx` (Stitch-derived, token-styled). A store owner cannot change them without a code change.
> - ❌ **Category tiles + "View All Categories" index page** — NOT DONE. Vehicle category is a plain string column on `vehicles` (economy/suv/luxury/van) with no separate `VehicleCategory` model, no `icon` field, and no `CategoryController::index()` / `Category/Index.tsx`.
> - ❌ **Newsletter signup** — NOT DONE. No `newsletter_subscribers` table, no subscribe endpoint, no newsletter form in the footer.

**Goal:** replace the default Laravel Welcome scaffold at `/` with a real
storefront homepage, following the Stitch mockup's structure: hero banner,
category tiles, featured vehicles, promo blocks, newsletter signup, existing
footer. Every visual element uses theme tokens, same rule as everything else.

**Three decisions, recommended defaults stated:**

**Decision 1 — Hero/promo content is admin-editable, not hardcoded.** A fleet
operator needs to change the hero headline/image and promo blocks without a code
deploy — same reasoning as `SiteIdentity` for the logo. One settings model plus
a repeater for promo blocks, managed in Filament. *(Not yet implemented — the
current hero is hardcoded.)*

**Decision 2 — "Featured Vehicles" ships as newest-available only for now, not
the mockup's Best Sellers/New Releases tabs.** "Best Sellers" needs sales-volume
aggregation from booking data, which doesn't exist as a queryable concept yet
(no per-vehicle booking count). Recommended: ship "New Releases" (reuses the
existing `NewestFirst` sort), flag "Best Sellers" as a clearly deferred future
enhancement once booking analytics exist — don't fake a tab that doesn't do
anything real. *(The featured-vehicles grid is implemented; a Best Sellers tab
is deferred.)*

**Decision 3 — Newsletter gets a real, minimal backend, not decorative-only
UI.** Shipping a newsletter section front and center with no backend would be
worse than a quiet footer gap. Recommended: a real `newsletter_subscribers` table
and endpoint — small, cheap, and this build doesn't ship non-functional UI as a
rule. *(Not yet implemented.)*

**Confirm before Step 1 if you want anything different.**

---

## 1. Homepage content settings (admin-editable hero + promo blocks) — NOT YET IMPLEMENTED

```php
Schema::create('homepage_content', function (Blueprint $table) {
    $table->id();
    $table->string('hero_title');
    $table->string('hero_subtitle')->nullable();
    $table->string('hero_cta_text')->nullable();
    $table->string('hero_cta_link')->nullable();
    $table->string('hero_image_path')->nullable();
    $table->timestamps();
});

Schema::create('homepage_promo_blocks', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('subtitle')->nullable();
    $table->string('cta_text')->nullable();
    $table->string('cta_link')->nullable();
    $table->string('image_path')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

Singleton `homepage_content` row (same pattern as `site_identity`), plus an
admin-managed repeater of promo blocks (the mockup shows 3 — e.g. "SUV Weekend
Deals," "Airport Transfer Fleet," "Long-Term Rental Rates" — but the count should
be however many the admin adds, not hardcoded to 3).

---

## 2. Category tiles — NOT YET IMPLEMENTED

Vehicle category is currently a plain string column on `vehicles`
(economy/suv/luxury/van), not a related model. To build the tiles section, add a
small category table (or, minimally, an icon mapping over the existing string
values):

```php
Schema::create('vehicle_categories', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('icon')->nullable(); // lucide-react icon name, e.g. 'Car', 'Truck'
    $table->timestamps();
});
```

`HomeController` queries categories ordered by published-vehicle count
descending, limited to the top 6-8 — a category with an unset icon falls back
to a generic default icon (a token-driven neutral icon, not a broken image).

**"View All Categories" needs a real destination** — currently only the fleet
listing exists, no index listing every category. Add `CategoryController::index()`
+ `Category/Index.tsx` (a simple grid of every category, reusing the same tile
component as the homepage section) — this closes a small gap the mockup itself
surfaces.

---

## 3. Featured vehicles ("New Releases")

Reuses existing infrastructure entirely — the newest available vehicles via the
already-built `NewestFirst` sort, rendered via the existing `vehicleCard`
layout variant (whichever one is currently active), same `VehicleCardProps`
contract as everywhere else. No new query logic, no new card design. **Already
implemented** — `routes/web.php` shares `featuredVehicles` (4 most recent
`available` vehicles) and `Home.tsx` renders them through
`<LayoutSlot name="vehicleCard" vehicle={vehicle} />`.

---

## 4. Newsletter signup — NOT YET IMPLEMENTED

```php
Schema::create('newsletter_subscribers', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->timestamps();
});
```

```php
public function subscribe(Request $request)
{
    $request->validate(['email' => 'required|email']);
    NewsletterSubscriber::firstOrCreate(['email' => $request->email]);
    // firstOrCreate rather than create+catch — resubscribing silently succeeds
    // rather than leaking "this email is already subscribed" (same privacy
    // instinct as the booking-tracking lookup's "no leak" design)
    return back()->with('success', 'Subscribed!');
}
```

Wire this real endpoint into the footer's newsletter form (currently absent).

---

## 5. HomeController assembles everything

```php
public function index()
{
    return Inertia::render('Home/Index', [
        'heroContent' => HomepageContent::first(),
        'promoBlocks' => HomepagePromoBlock::where('is_active', true)->orderBy('sort_order')->get(),
        'categories' => VehicleCategory::withCount(['vehicles' => fn ($q) => $q->where('status', 'available')])
            ->orderByDesc('vehicles_count')->limit(8)->get(),
        'featuredVehicles' => Vehicle::where('status', 'available')->latest()->limit(4)->get(/* card-shaped */),
    ]);
}
```

Route: `/` → `HomeController::index()` (currently a closure in `routes/web.php`),
wrapped in `PublicLayout` (full header/footer, same as every other storefront
page — no reason for the homepage to be an exception to that rule).

---

## 6. Frontend structure — separate components, not a registry (yet)

Build as clearly separated components (`Hero.tsx`, `CategoryTiles.tsx`,
`FeaturedVehicles.tsx`, `PromoGrid.tsx`, `NewsletterSection.tsx`) composed in
`Home/Index.tsx` — not a `LayoutVariantRegistry` region. There's only one
homepage design being built right now; making it swappable before a second
design actually exists would be the same premature-registry mistake this build
has avoided everywhere else (the deferred attribute-type registry). If a client
ever wants a second homepage layout, *that's* the moment to introduce a
`homepage-layout` region — not now. *(The current `Home.tsx` is a single
composed page; the admin-editable pieces above don't exist yet.)*

All components token-driven, no hardcoded colors/fonts/spacing — same rule as
every component in this build.

---

## 7. Admin (Filament)

- `HomepageContentSettings` page (hero fields, image upload) — `Admin`-only
- A relation manager or repeater for promo blocks (add/edit/reorder/deactivate)
- `VehicleCategoryResource`'s edit form gets an `icon` field (a simple text input
  for the lucide icon name, or a searchable select if you want to constrain it to
  valid icon names — either is fine, don't over-engineer this)

---

## 8. Build order

1. Migrations: `homepage_content`, `homepage_promo_blocks`,
   `newsletter_subscribers`, `vehicle_categories` — ask before running
2. `HomepageContentSettings` + promo block management in Filament
3. `HomeController::index()`, route change from the current closure to this
4. `CategoryController::index()` + `Category/Index.tsx` (closes the "View All
   Categories" gap)
5. Frontend components (section 6), composed in `Home/Index.tsx`
6. Newsletter endpoint + footer form
7. Verify:
   - Visit `/`, confirm the real homepage renders (not Welcome.tsx), matching
     the mockup's structure under the currently active theme
   - Confirm hero content is editable from Filament and reflects immediately
   - Add/reorder/deactivate a promo block, confirm it reflects on the homepage
   - Confirm category tiles show real categories with real vehicle counts,
     clicking one navigates to that category's page
   - Confirm "View All Categories" goes to a real, working index page
   - Confirm featured vehicles show the 4 newest available vehicles, using
     whichever vehicle-card variant is currently active
   - Subscribe to the newsletter with a real email, confirm a row is created;
     resubscribe with the same email, confirm it succeeds silently (no error,
     no duplicate row)
   - Switch themes, confirm the whole homepage re-skins correctly with zero
     hardcoded values anywhere
   - Confirm the homepage still works correctly for a fleet with
     zero categories or zero vehicles (empty states, not errors)
