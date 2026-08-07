# Fleet Browsing & Filtering — Registry-Based for Easy Future Extension

> **Adapted from e-commerce category-browsing-filtering.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ✅ **Public fleet listing page** — DONE. `/vehicles` (`VehicleController::index()` in the `fleet-management` plugin) lists `available` vehicles, applies the `vehicle.listQuery` filter, paginates 12, and renders `Vehicles/Index.tsx`.
> - ✅ **Registry-based filtering + sorting, server-side** — DONE (2026-08-07). `VehicleFilterProvider`/`VehicleFilterRegistry` (category + transmission, case-insensitive) and `VehicleSortOption`/`VehicleSortRegistry` (`price_asc`/`price_desc`/`name_asc`) live in core, registered in `AppServiceProvider::boot()`. `VehicleController::index()` orchestrates both registries plus free-text `search`, then composes `vehicle.listQuery` before paginating. `Vehicles/Index.tsx` renders the FilterBar generically from the `availableFilters`/`availableSorts` props and pushes every change through `router.get()` (`preserveState`, `replace`), so filters/sort/search persist in the URL. See `docs/event-registry.md`'s "Fleet-listing filter/sort registries" section.
> - ❌ **`MinDailyRateFilter` / `MaxDailyRateFilter` (daily-rate range)** — NOT DONE. The registry supports a `range` `uiType`, but no range filter is registered yet; the current filters are `category` and `transmission` (both `select`).
> - ❌ **Admin control (`catalog_control_settings`) gating which filters/sorts are enabled** — NOT DONE. No table, no model, no `applyAll()` disable check, no Filament settings page.
> - ❌ **Admin control (`catalog_control_settings`) gating which filters/sorts are enabled** — NOT DONE. No table, no model, no `applyAll()` disable check, no Filament settings page.
> - ❌ **Vehicle category storefront pages** — NOT DONE. Category is a plain string column on `vehicles` (economy/suv/luxury/van); there is no `VehicleCategory` model, no `/categories/{slug}` route, and no `Category/Show.tsx`.
> - ❌ **Search vs. filter reconciliation** — still a deliberately deferred future phase, unchanged.

**Goal:** the fleet listing already exists and vehicle category is already shown
on the vehicle detail page, but there's no browsing by category and the listing's
filtering/sorting is hardcoded client-side. This closes both gaps — **using the
same registry pattern as payments/shipping/promotions/recommendations/search**,
so adding a new filter type later (by attribute, by seat count, by rating once
reviews mature) means registering a new provider, not editing
`VehicleController` again.

This is the difference between "it works today" and "it stays easy to maintain" —
worth the small amount of extra structure up front.

---

## 1. Vehicle category storefront pages — NOT YET IMPLEMENTED

```php
// route
Route::get('/categories/{category:slug}', [CategoryController::class, 'show']);
```

```php
// CategoryController::show()
public function show(VehicleCategory $category, Request $request)
{
    $vehicles = VehicleFilterRegistry::applyAll(
        Vehicle::where('status', 'available')->where('category_id', $category->id),
        $request->all()
    )->paginate(12);

    return Inertia::render('Category/Show', ['category' => $category, 'vehicles' => $vehicles]);
}
```

Reuses the exact same vehicle listing UI/card rendering as `/vehicles` — this is
a filtered version of the same page, not a new design. *(Requires first promoting
vehicle category from a string column to a real `vehicle_categories` table.)*

---

## 2. The registry — this is what makes future filters cheap to add — NOT YET IMPLEMENTED

```php
// app/Core/Contracts/VehicleFilterProvider.php
interface VehicleFilterProvider
{
    public function id(): string;              // 'category' | 'daily-rate-range' | future: 'attribute-color'
    public function label(): string;
    public function uiType(): string;            // 'select' | 'range' | 'checkbox'
    public function options(): ?array;            // for 'select': [{value, label}], null otherwise
    public function apply(Builder $query, mixed $value): Builder;
}
```

```php
// app/Core/Support/VehicleFilterRegistry.php
class VehicleFilterRegistry
{
    protected static array $filters = [];

    public static function register(VehicleFilterProvider $filter): void
    {
        static::$filters[$filter->id()] = $filter;
    }

    public static function all(): array { return static::$filters; }

    /** Applies every registered filter that has a value present in the request input */
    public static function applyAll(Builder $query, array $input): Builder
    {
        foreach (static::$filters as $id => $filter) {
            if (array_key_exists($id, $input) && $input[$id] !== null && $input[$id] !== '') {
                $query = $filter->apply($query, $input[$id]);
            }
        }
        return $query;
    }
}
```

Core registers the two starting filters — same "build two, prove the pattern"
rule as every other registry in this build:

```php
// app/Core/Filters/VehicleCategoryFilter.php
class VehicleCategoryFilter implements VehicleFilterProvider
{
    public function id(): string { return 'category'; }
    public function label(): string { return 'Category'; }
    public function uiType(): string { return 'select'; }
    public function options(): ?array { return VehicleCategory::pluck('name', 'id')->map(fn ($name, $id) => ['value' => $id, 'label' => $name])->values()->toArray(); }
    public function apply(Builder $query, mixed $value): Builder { return $query->where('category_id', $value); }
}

// app/Core/Filters/DailyRateRangeFilter.php — registers as TWO entries, min and max,
// since they're independent inputs even though conceptually one "filter"
class MinDailyRateFilter implements VehicleFilterProvider
{
    public function id(): string { return 'min_daily_rate'; }
    public function label(): string { return 'Min Daily Rate'; }
    public function uiType(): string { return 'range'; }
    public function options(): ?array { return null; }
    // daily_rate is a decimal stored as-is (e.g. 350.00), NOT a cent integer
    public function apply(Builder $query, mixed $value): Builder { return $query->where('daily_rate', '>=', $value); }
}
// MaxDailyRateFilter mirrors this with '<='
```

A future plugin (e.g. a "filter by attribute" addition once a fleet wants it)
registers its own filter the same way, from its own Service Provider, without
touching `VehicleController` or this file at all.

---

## 3. Sort — same registry idea, separate small registry — NOT YET IMPLEMENTED

```php
// app/Core/Contracts/VehicleSortOption.php
interface VehicleSortOption
{
    public function id(): string;   // 'newest' | 'daily_rate_asc' | 'daily_rate_desc'
    public function label(): string;
    public function apply(Builder $query): Builder;
}
```

```php
// VehicleSortRegistry — register/all/get, same shape
VehicleSortRegistry::register(new NewestFirst());   // default
VehicleSortRegistry::register(new DailyRateAscending());
VehicleSortRegistry::register(new DailyRateDescending());
```

A future sort option ("Best selling" once booking data supports it, "Highest
rated" once reviews mature) is a new class + one registration call, not a new
`match()` branch in the controller.

---

## 4. VehicleController — now just orchestrates the registries

```php
public function index(Request $request)
{
    $query = Vehicle::where('status', 'available');
    $query = VehicleFilterRegistry::applyAll($query, $request->all());

    $sortId = $request->input('sort', 'newest');
    $sort = VehicleSortRegistry::get($sortId) ?? VehicleSortRegistry::get('newest');
    $query = $sort->apply($query);

    $vehicles = FilterRegistry::apply('vehicle.listQuery', $query)->paginate(12);
    // existing vehicle.listQuery hook still composes here — vehicle-media's
    // eager-load pipe and this new filter/sort layer are independent and stack cleanly

    return Inertia::render('Vehicles/Index', [
        'vehicles' => $vehicles,
        'availableFilters' => collect(VehicleFilterRegistry::all())->map(fn ($f) => [
            'id' => $f->id(), 'label' => $f->label(), 'uiType' => $f->uiType(), 'options' => $f->options(),
        ])->values(),
        'availableSorts' => collect(VehicleSortRegistry::all())->map(fn ($s) => ['id' => $s->id(), 'label' => $s->label()])->values(),
    ]);
}
```

*(The current `VehicleController::index()` does NOT do this yet — it paginates the
available-vehicle query through `vehicle.listQuery` and leaves filtering/sorting to
the client-side `Index.tsx`.)*

---

## 5. Frontend — renders filter controls generically from what's registered

Same "read definitions, render the matching input type" pattern as the
preferences form and Filament's dynamic attribute form — not hardcoded per-filter
markup:

```tsx
// resources/js/Components/FilterBar.tsx
export function FilterBar({ availableFilters, availableSorts, currentValues }) {
  return (
    <div className="flex gap-4">
      {availableFilters.map(f => {
        if (f.uiType === 'select') return <select key={f.id} name={f.id} defaultValue={currentValues[f.id]}>{f.options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}</select>;
        if (f.uiType === 'range') return <input key={f.id} type="number" name={f.id} placeholder={f.label} defaultValue={currentValues[f.id]} />;
        return null; // 'checkbox' type, when a future filter needs it
      })}
      <select name="sort" defaultValue={currentValues.sort}>
        {availableSorts.map(s => <option key={s.id} value={s.id}>{s.label}</option>)}
      </select>
    </div>
  );
}
```

A future filter type appearing in `availableFilters` renders automatically —
**no frontend code change needed** for a new `select` or `range` filter, only for
a genuinely new `uiType` the renderer doesn't yet handle. *(The current fleet
page's filter/sort UI is hand-built in `Index.tsx`, not driven from a registry.)*

---

## 6. Admin control — same "pick which registered things are active" pattern as PluginResource/LayoutSettings — NOT YET IMPLEMENTED

Every registered filter/sort is usable by default, but an admin should be able to
turn individual ones off (hide the price filter, disable a sort option) and
reorder how they appear — without a code change, same as picking an active layout
variant.

```php
// migration — one small table for both filters and sorts
Schema::create('fleet_control_settings', function (Blueprint $table) {
    $table->id();
    $table->enum('control_type', ['filter', 'sort']);
    $table->string('control_id'); // matches VehicleFilterProvider::id() or VehicleSortOption::id()
    $table->boolean('is_enabled')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->unique(['control_type', 'control_id']);
});
```

**Critical: the enabled/disabled setting must gate at the `applyAll()` level, not
just hide the frontend control.** A disabled filter must not be usable even if a
customer hand-crafts the query string — otherwise the toggle is cosmetic, the
same class of bug as a client-side-only restriction that doesn't hold up
server-side.

```php
// VehicleFilterRegistry::applyAll() — only applies filters that are BOTH
// registered AND not explicitly disabled
public static function applyAll(Builder $query, array $input): Builder
{
    $disabled = FleetControlSetting::where('control_type', 'filter')
        ->where('is_enabled', false)->pluck('control_id');

    foreach (static::$filters as $id => $filter) {
        if ($disabled->contains($id)) continue; // skip even if present in $input
        if (array_key_exists($id, $input) && $input[$id] !== null && $input[$id] !== '') {
            $query = $filter->apply($query, $input[$id]);
        }
    }
    return $query;
}
```

If no settings row exists for a given filter/sort, it defaults to enabled — same
"sensible default without explicit configuration" behavior as `LayoutSetting`
falling back to the first registered variant when nothing's been chosen yet.

Admin UI: a Filament settings page (`FleetControlSettings`, `Admin`-only via
`HasMinimumRole`) listing every registered filter and sort with a toggle and
drag-to-reorder — same shape as `LayoutSettings`'s dropdowns, just a toggle+order
list instead. The frontend's `availableFilters`/`availableSorts` props (section 5)
only include enabled entries, in the configured order.

---

## 7. Search vs. filter — same deferred decision as before, unchanged

Meilisearch search and this registry-based Eloquent filtering remain separate
paths for this phase (searching clears filters, filtering clears search).
Reconciling them into one combined system is real, separate work — flag it as a
deliberately deferred future phase, not something to half-merge here. *(This
project has no Meilisearch integration at all yet — see 17-SEARCH-SUGGESTIONS.md.)*

---

## 8. Build order

1. `VehicleFilterProvider`/`VehicleFilterRegistry`, `VehicleCategoryFilter` +
   `MinDailyRateFilter`/`MaxDailyRateFilter` registered
2. `VehicleSortOption`/`VehicleSortRegistry`, three starting sort options
3. `fleet_control_settings` migration (ask before running), `FleetControlSetting`
   model, `applyAll()` updated to check disabled state (section 6) — this is the
   part that has to actually gate application, not just hide UI
4. Promote vehicle category from a string column to a `vehicle_categories` table
   (ask before the migration), then `CategoryController::show()` + route +
   `Category/Show.tsx`
5. `VehicleController::index()` rewired to orchestrate both registries, only
   exposing enabled filters/sorts to the frontend in configured order
6. `FleetControlSettings` Filament page, Admin-only
7. `FilterBar` component rendering generically from `availableFilters`/
   `availableSorts` (section 5) — and `Vehicles/Index.tsx` consuming it
8. Wire real category links into the storefront header's nav dropdown
   (replacing the current no-destination state)
9. Verify:
   - Visit a category page, confirm only that category's available vehicles show
   - Filter by daily-rate range via the generic FilterBar, confirm correct results
   - Sort by daily rate ascending/descending, confirm correct order
   - Combine category + daily-rate filter together, confirm both apply
   - **Prove the registry actually helps**: register one throwaway test filter
     purely to confirm it appears in the frontend FilterBar automatically with
     zero frontend code changes — then remove it
   - **Prove the admin toggle is a real gate, not cosmetic**: disable the price
     filter via `FleetControlSettings`, confirm it disappears from the FilterBar,
     AND confirm hand-typing `?min_daily_rate=100` in the URL directly no longer
     filters results — this is the concrete proof the toggle enforces
     server-side, not just hides a UI control. Re-enable it, confirm it works
     again both ways.
   - Confirm the header's category links navigate to real, working pages
   - Confirm a category with zero available vehicles shows a clean empty state
