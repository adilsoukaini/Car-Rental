# Analytics Dashboard — Fixed Widgets Done, Extensible Builder Deferred

> **Adapted from e-commerce analytics-dashboard.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ✅ **Three fixed Filament widgets** — DONE (`app/Filament/Widgets/`): `BookingStatsOverview` ("Total Booking Value"), `BookingVolumeChart`, and `VehicleUtilizationTable`, all auto-discovered via the panel's existing `discoverWidgets()` wiring. Values verified with exact numbers against real seeded data.
> - ✅ **Honest "Total Booking Value" naming** — DONE. `PaymentGateway::chargeFinal()` (the only place a booking's `total_price` would be captured) has zero real callers — the only real money movement is the deposit hold — so the metric is deliberately labeled "Total Booking Value" (sum of `confirmed`/`checked_out`/`returned` bookings), not "Revenue".
> - ✅ **Status-filter distinction across widgets** — DONE. `BookingStatsOverview` counts only `confirmed`/`checked_out`/`returned`; `BookingVolumeChart` additionally counts `cancelled`; both exclude `pending`/`expired`.
> - ❌ **Extensible widget builder (the Grafana-style system)** — NOT DONE, deliberately skipped. There is NO `DashboardWidgetTemplate` contract, NO `DashboardWidgetRegistry`, NO `dashboard_widget_instances` table, and NO custom add/remove/reorder admin builder page. The three fixed widgets above are the entire analytics story. This was an explicit decision: multiple independent plugins competing for dashboard space is a real need in the source project, but not in this one — don't build a second extensibility layer for a need that doesn't exist.

**Goal (of the deferred builder, kept as design for when it's needed):** an admin
can add, remove, reorder, and configure dashboard widgets from a template library —
not only the three fixed Filament widgets, but a genuine "pick a widget type, drop
it on the dashboard, configure its settings" builder. Same registry pattern as
`LayoutVariantRegistry`, same generic config-form pattern as the vehicle-attributes
admin form — applied to dashboard widgets instead of layout regions or user
preferences.

**One honest technical reality, stated up front, not discovered mid-build**:
Filament's own widget system is normally a static PHP array configured per
page — it isn't built for an end-user to add/remove/reorder widgets at
runtime. Getting real Grafana-style behavior means building a **custom admin
page with its own Livewire-driven rendering logic** that reads widget
placements from a table and dynamically dispatches to the right chart/stat
rendering per widget type — not just registering more standard Filament
`ChartWidget`/`BaseWidget` classes on a fixed page. This is genuine, separate
engineering.

---

## 1. Core contract — what a widget template is — NOT YET IMPLEMENTED

```php
// app/Core/Contracts/DashboardWidgetTemplate.php
interface DashboardWidgetTemplate
{
    public function id(): string;              // 'volume-chart' | 'stats-overview' | ...
    public function label(): string;
    public function description(): string;
    public function widgetType(): string;        // 'stat' | 'line-chart' | 'donut-chart' | 'table'
    public function configSchema(): array;        // [{key, label, type, options?, default}] —
                                                     // same shape as PreferenceRegistry's field descriptions
    public function getData(array $config): array; // returns render-ready data given this instance's config
}
```

```php
// app/Core/Support/DashboardWidgetRegistry.php
// register($template, $pluginSlug), all(), get($id) — same shape as every
// other registry in this build
```

Core registers the starting templates (the three fixed widgets become the first
registered templates):

```php
class BookingVolumeChartTemplate implements DashboardWidgetTemplate
{
    public function id(): string { return 'volume-chart'; }
    public function label(): string { return 'Booking Volume Over Time'; }
    public function widgetType(): string { return 'line-chart'; }
    public function configSchema(): array {
        return [['key' => 'period_days', 'label' => 'Period', 'type' => 'select',
                 'options' => ['7' => '7 days', '30' => '30 days', '90' => '90 days'], 'default' => '30']];
    }
    public function getData(array $config): array {
        $days = (int) ($config['period_days'] ?? 30);
        return Booking::whereIn('status', ['confirmed', 'checked_out', 'returned', 'cancelled'])
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')->orderBy('date')->get()->toArray();
    }
}
// StatsOverviewTemplate (Total Booking Value), VehicleUtilizationTemplate follow the same shape
```

A future plugin (e.g. a loyalty-points plugin wanting a "Points Redeemed"
widget) registers its own template the same way, from its own Service
Provider — no core file touched.

---

## 2. Data — what's actually placed on the dashboard — NOT YET IMPLEMENTED

```php
Schema::create('dashboard_widget_instances', function (Blueprint $table) {
    $table->id();
    $table->string('widget_template_id'); // matches DashboardWidgetTemplate::id()
    $table->json('config'); // this instance's chosen settings, per configSchema
    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('is_visible')->default(true);
    $table->timestamps();
});
```

One row per widget an admin has placed — an admin could place "Booking Volume
Over Time" twice with different `period_days` configs (a 7-day view and a 30-day
view side by side), which is exactly the kind of flexibility a fixed-widget
approach couldn't offer.

---

## 3. Admin builder page — the real engineering work — NOT YET IMPLEMENTED

A custom Filament `Page` (not a `Resource`, not standard `getWidgets()`) with
its own Livewire component:

- Renders every `dashboard_widget_instances` row (ordered by `sort_order`,
  `is_visible = true`) by looking up its template via `DashboardWidgetRegistry`
  and calling `getData($config)`, then dispatching to the right chart/stat/table
  rendering based on `widgetType()`
- **"Add Widget"** button opens a picker sourced from
  `DashboardWidgetRegistry::all()` — selecting one creates a new
  `dashboard_widget_instances` row with `configSchema()`'s defaults
- Each placed widget gets a **settings icon** opening a form generated from
  its template's `configSchema()` — same "describe the field, render the
  matching control" pattern as the preferences form and the vehicle-attributes
  dynamic Filament form, applied here to widget configuration instead
- **Remove** button per widget, **drag-to-reorder** updating `sort_order`
- `Admin`-only access (`HasMinimumRole`)

---

## 4. Frontend rendering per `widgetType`

A small dispatcher (Blade/Livewire, or a React island if the admin panel
supports it) mapping `widgetType()` to an actual rendering component:
`stat` → a number card, `line-chart`/`donut-chart` → a chart library render
(Filament's own chart widgets use Chart.js under the hood — reuse that same
library directly rather than adding a second charting dependency), `table` →
a simple data table. This is the one place genuinely new code is needed, since
none of the existing chart-rendering code was built to be data-driven by an
arbitrary runtime config.

---

## 5. What's already built, and what the deferred builder must reuse

Already implemented and verified (do not rebuild):
- `BookingStatsOverview` — "Total Booking Value", average value, distinct-customer
  counts over `confirmed`/`checked_out`/`returned` bookings only.
- `BookingVolumeChart` — bookings per day, including `cancelled`, excluding
  `pending`/`expired`.
- `VehicleUtilizationTable` — per-vehicle utilization percentage over a 30-day
  window, batch-loading all vehicles + their overlapping bookings in exactly 2
  queries (rule 8).

When the builder is built, these three become the first registered templates, with
their real query logic moved into `DashboardWidgetTemplate::getData()` methods.

---

## 6. Build order (only when the builder is actually wanted)

1. `DashboardWidgetTemplate` interface, `DashboardWidgetRegistry`,
   `dashboard_widget_instances` migration — ask before running
2. Convert the three existing widgets into the first three templates (reusing
   their verified query logic — Total Booking Value/booking counts only, same
   status filters)
3. The custom admin builder page — start with just "render what's placed,"
   before adding "add/remove/reorder" — prove rendering works against real
   data first, same incremental discipline as every other phase
4. "Add Widget" picker + instance creation
5. Generic config-form-per-widget (settings icon), same pattern as preferences
6. Remove + drag-to-reorder
7. Verify, with real evidence:
   - Add all three starting widget templates to the dashboard, confirm each
     renders correct data (verify against manual DB queries, same standard as
     the original value-accuracy requirement — a wrong number here is still a
     wrong number regardless of how the widget got placed)
   - Add "Booking Volume Over Time" a SECOND time with a different period config,
     confirm both instances render independently and correctly — the concrete
     proof the system is genuinely more flexible than fixed widgets
   - Remove a widget, confirm it disappears and the row is actually removed
     (or hidden, your call — state which)
   - Reorder widgets, confirm the new order persists across a page reload
   - Register one throwaway test widget template purely to confirm it appears
     in the "Add Widget" picker with zero core code changes — then remove it
   - Confirm a Staff-role user cannot access the builder page
