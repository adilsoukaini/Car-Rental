# Layout Variant System — Swappable Regions Without Losing Functionality

> **Adapted from e-commerce layout-variants-design.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ✅ **`LayoutVariantRegistry`** — DONE (`app/Core/Support/LayoutVariantRegistry.php`). NOTE: this class was documented in `docs/event-registry.md` as "planned, not implemented" for most of the project's life; it became real once the Stitch design source produced genuinely different layouts (the fleet listing, vehicle detail, checkout, and gallery screens). It is now a live mechanism with four regions registered in `AppServiceProvider::boot()`.
> - ✅ **`layout_settings` table + `App\Models\LayoutSetting`** — DONE (`2026_08_06_000002_create_layout_settings_table.php`).
> - ✅ **Active-variant sharing** — DONE. `HandleInertiaRequests::share()` resolves every registered slot via `LayoutVariantRegistry::activeComponentFor()` (guarded by `Schema::hasTable('layout_settings')` so a fresh install doesn't crash).
> - ✅ **`LayoutSlot` component + `layoutComponentRegistry.tsx`** — DONE (`resources/js/layoutComponentRegistry.tsx`).
> - ✅ **`LayoutSettings` Filament page** — DONE (`app/Filament/Pages/LayoutSettings.php`), `Admin`-only.
> - ✅ **Implemented regions** (differ from the source doc's header/footer/card set — see the adaptation note below):
>   - `vehicleCard` — `vertical` (default) / `horizontal-split` → `Layout/VehicleCard/Vertical` / `Layout/VehicleCard/HorizontalSplit` (rendered via `<LayoutSlot name="vehicleCard">` on the homepage and fleet listing)
>   - `fleetLayout` — `default` (Inline Search) / `sidebar` (Sidebar Search); read directly by `Vehicles/Index.tsx` (no `LayoutSlot` — the page switches its own render)
>   - `reviewDisplay` — `card-list` (default) / `compact` → `Widgets/VehicleReviewsCardList` / `Widgets/VehicleReviewsCompact` (rendered via `<LayoutSlot name="reviewDisplay">` on `Vehicles/Show.tsx`)
>   - `vehicle-gallery` — `single-hero` (default) / `carousel` → `Components/VehicleGallery` / `Components/VehicleGalleryCarousel` (rendered via `<LayoutSlot name="vehicle-gallery">` on `Vehicles/Show.tsx`)
>   - `checkoutStyle` — `sidebar-flow` (default) / `vertical-stack`; read directly by `Bookings/Checkout.tsx`
> - ❌ **Header/footer as swappable variants** — NOT DONE. The storefront uses a single fixed `PublicLayout.tsx` (sticky blurred header, dark multi-column footer); there is no `header`/`footer` region in the registry. The mechanism supports it, but no second header/footer design exists yet.

**Adaptation note (regions):** the source doc's example regions were
`header`/`footer`/`product-card`. In this car-rental build the regions that
actually need swapping are the **vehicle card** (`vehicleCard`), the **fleet
listing layout** (`fleetLayout`), the **reviews display** (`reviewDisplay`), the
**vehicle gallery** (`vehicle-gallery`), and the **booking checkout layout**
(`checkoutStyle`) — the areas where the Stitch design source contains genuinely
different arrangements of the same data. Header and footer were built as one
fixed `PublicLayout` and have no second design yet, so no region exists for them.
Every other line of the architecture below is unchanged.

**Goal:** swap *which component* renders a structural region (vehicle card, fleet
listing, reviews display, vehicle gallery, checkout layout) from the admin panel —
not just its colors/fonts (that's the existing theme system), but its actual layout
and behavior — while guaranteeing every variant still does everything that region
is required to do, and every variant still obeys whichever theme is active.

This is a different axis from theming and a different mechanism from slots:

| System | Answers | Mechanism |
|---|---|---|
| **Theme** (already built) | "What does everything look like?" (color/font/spacing) | CSS variables from token files |
| **Slots** (already built) | "What extra stuff gets inserted at this point?" | Named insertion points, additive |
| **Layout variants** (this doc) | "Which whole implementation renders this structural region?" | Contract + variant registry, one active choice per region |

---

## 1. The core idea: a props contract per swappable region

Every swappable region has a **TypeScript interface** defining exactly what
functionality it must support. Any variant — however different it looks — must accept
these exact props. This is what prevents "picking a different card loses the book
button": a variant that doesn't wire up the expected props is a compile error, not a
runtime surprise discovered after an admin has already picked it.

```typescript
// resources/layout-contracts/VehicleCardProps.ts
export interface VehicleCardProps {
  vehicle: { id: number; make: string; model: string; dailyRate: string; category: string; primaryImage: { url: string; altText: string | null } | null };
  // the whole card is a single Link to vehicles.show; no action callback needed
  // (unlike the source doc's onAddToCart — booking happens on the detail page)
}
```

```typescript
// resources/layout-contracts/VehicleReviewsProps.ts
export interface VehicleReviewsProps {
  averageRating: number;
  reviewCount: number;
  reviews: {
    id: number; authorInitials: string; authorName: string; rating: number;
    title: string | null; body: string; isVerifiedRental: boolean; createdAt: string;
  }[];
}
```

*(The gallery (`vehicle-gallery`), fleet-layout, and checkout-style regions pass
looser props today — gallery passes `images`, fleet-layout and checkout-style are
switched by the page reading the active component name directly rather than via a
`LayoutSlot`. The card and reviews regions are the ones with real, distinct
components registered in `layoutComponentRegistry.tsx`.)*

Start with the regions that genuinely have more than one design, and add more
later the same way (hero banner, homepage layout) once the pattern needs it.

**Rule:** a contract's required props represent functionality that must never be lost.
Optional props (`?`) represent functionality that depends on other plugins being
enabled — a variant is free to ignore an optional prop, but must not break if it's
absent.

---

## 2. The registry — same shape as PaymentGatewayRegistry — DONE

```php
// app/Core/Support/LayoutVariantRegistry.php
namespace App\Core\Support;

class LayoutVariantRegistry
{
    protected static array $variants = []; // slotName => [ {variantId, label, componentName, pluginSlug} ]

    public static function register(string $slotName, string $variantId, string $label, string $componentName, string $pluginSlug = 'core'): void
    {
        static::$variants[$slotName][] = compact('variantId', 'label', 'componentName', 'pluginSlug');
    }

    /** Every registered option for a region — used to populate the admin picker */
    public static function availableFor(string $slotName): array
    {
        return static::$variants[$slotName] ?? [];
    }

    /** Which component name is currently active for this region, for this deployment */
    public static function activeComponentFor(string $slotName): string
    {
        $activeId = \App\Models\LayoutSetting::where('slot_name', $slotName)->value('active_variant_id');
        $options = static::$variants[$slotName] ?? [];

        $chosen = collect($options)->firstWhere('variantId', $activeId)
            ?? $options[0] ?? null; // fall back to first registered variant if none chosen yet

        if (!$chosen) {
            throw new \RuntimeException("No layout variant registered for slot '{$slotName}'.");
        }

        return $chosen['componentName'];
    }
}
```

Core registers its default variants at boot; plugins can register additional ones
from their own Service Providers — identical pattern to payments gateways:

```php
// app/Providers/AppServiceProvider.php (core defaults, always present)
LayoutVariantRegistry::register('vehicleCard', 'vertical', 'Vertical', 'Layout/VehicleCard/Vertical');
LayoutVariantRegistry::register('vehicleCard', 'horizontal-split', 'Horizontal Split', 'Layout/VehicleCard/HorizontalSplit');
LayoutVariantRegistry::register('fleetLayout', 'default', 'Inline Search', 'fleet-layout-default', 'fleet-management');
LayoutVariantRegistry::register('fleetLayout', 'sidebar', 'Sidebar Search', 'fleet-layout-sidebar', 'fleet-management');
LayoutVariantRegistry::register('reviewDisplay', 'card-list', 'Card List', 'Widgets/VehicleReviewsCardList', 'reviews');
LayoutVariantRegistry::register('reviewDisplay', 'compact', 'Compact', 'Widgets/VehicleReviewsCompact', 'reviews');
LayoutVariantRegistry::register('vehicle-gallery', 'single-hero', 'Single Hero', 'Components/VehicleGallery', 'vehicle-media');
LayoutVariantRegistry::register('vehicle-gallery', 'carousel', 'Carousel', 'Components/VehicleGalleryCarousel', 'vehicle-media');
LayoutVariantRegistry::register('checkoutStyle', 'sidebar-flow', 'Sidebar Flow', 'checkout-sidebar', 'booking-engine');
LayoutVariantRegistry::register('checkoutStyle', 'vertical-stack', 'Vertical Stack', 'checkout-vertical', 'booking-engine');
```

*(Note: `vehicleCard`, `fleetLayout`, `reviewDisplay`, `vehicle-gallery`, and
`checkoutStyle` are registered in core because the consuming components/pages are
core-owned — the plugin slug is metadata for the admin picker, not a class
reference, keeping Hard Rule 1 intact.)*

```php
// a future plugin, e.g. plugins/seasonal-sale/src/SeasonalSaleServiceProvider.php
public function boot()
{
    LayoutVariantRegistry::register('vehicleCard', 'holiday-badge', 'Holiday Card', 'SeasonalSale/HolidayCard', 'seasonal-sale');
}
```

---

## 3. Data: one table, one row per swappable region per deployment — DONE

```php
// database/migrations/xxxx_create_layout_settings_table.php
Schema::create('layout_settings', function (Blueprint $table) {
    $table->id();
    $table->string('slot_name')->unique();   // 'vehicleCard' | 'fleetLayout' | 'reviewDisplay' | ...
    $table->string('active_variant_id');
    $table->timestamps();
});
```

```php
// app/Models/LayoutSetting.php
class LayoutSetting extends Model
{
    protected $fillable = ['slot_name', 'active_variant_id'];
}
```

---

## 4. Wiring: Inertia shared prop + a component registry, same shape as plugin slots — DONE

Share the active variant map on every request, same mechanism as `activeTheme`:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'activeTheme' => config('site.active_theme'),
        'activeLayoutVariants' => [
            'vehicleCard' => LayoutVariantRegistry::activeComponentFor('vehicleCard'),
            'reviewDisplay' => LayoutVariantRegistry::activeComponentFor('reviewDisplay'),
            'vehicle-gallery' => LayoutVariantRegistry::activeComponentFor('vehicle-gallery'),
            // fleetLayout / checkoutStyle are read directly by their pages
        ],
    ]);
}
```

*(The real implementation iterates `LayoutVariantRegistry::allRegisteredSlots()`
so every registered region is shared automatically, guarded by
`Schema::hasTable('layout_settings')`.)*

React-side component registry maps component names to actual lazy-loaded components —
same pattern as `pluginComponentRegistry.tsx`:

```tsx
// resources/js/layoutComponentRegistry.tsx
const registry: Record<string, React.ComponentType<any>> = {
  'Layout/VehicleCard/Vertical': lazy(() => import('@/Layout/VehicleCard/Vertical')),
  'Layout/VehicleCard/HorizontalSplit': lazy(() => import('@/Layout/VehicleCard/HorizontalSplit')),
  'Widgets/VehicleReviewsCardList': lazy(() => import('@/Widgets/VehicleReviewsCardList')),
  'Widgets/VehicleReviewsCompact': lazy(() => import('@/Widgets/VehicleReviewsCompact')),
  'Components/VehicleGallery': lazy(() => import('@/Components/VehicleGallery')),
  'Components/VehicleGalleryCarousel': lazy(() => import('@/Components/VehicleGalleryCarousel')),
};

export function LayoutSlot<P extends object>({ name, ...props }: { name: string } & P) {
  const { activeLayoutVariants } = usePage().props as any;
  const componentName = activeLayoutVariants[name];
  const Component = registry[componentName];
  if (!Component) return null; // unknown region renders nothing, never crashes
  return <Suspense fallback={null}><Component {...props} /></Suspense>;
}
```

Core layout files never hardcode which region renders — they render the slot:

```tsx
// resources/js/Pages/Vehicles/Index.tsx — vehicle cards through the slot
{vehicles.data.map(v => (
  <LayoutSlot key={v.id} name="vehicleCard" vehicle={v} />
))}
```

```tsx
// resources/js/Pages/Vehicles/Show.tsx — reviews and gallery through slots
<LayoutSlot name="vehicle-gallery" images={galleryImages} />
<LayoutSlot name="reviewDisplay" vehicleId={vehicle.id} reviewsData={reviewsData} />
```

`Vehicles/Index.tsx` and `Bookings/Checkout.tsx` read `fleetLayout`/`checkoutStyle`
directly from the shared prop and switch their own render (no `LayoutSlot`, since
the whole page layout — not a single component — is what changes).

---

## 5. Every variant follows the same two rules as any other component

1. **Must satisfy the region's props contract in full** (required props implemented,
   optional props gracefully ignored if absent) — enforced by TypeScript at build time.
2. **Must use theme tokens only** — no hardcoded colors/fonts/spacing. A vertical
   card and a horizontal-split card both render in the active client's palette, and
   both obey the active theme's spacing/radius. Variant choice and theme choice are
   fully independent and compose freely — any variant × any theme is a valid
   combination, which is exactly the point.

```tsx
// resources/js/Layout/VehicleCard/Vertical.tsx
import { VehicleCardProps } from '@/layout-contracts/VehicleCardProps';

export default function Vertical({ vehicle }: VehicleCardProps) {
  return (
    <Link href={route('vehicles.show', vehicle.id)}
          className="group flex flex-col overflow-hidden rounded-container border border-border bg-surface shadow-resting transition hover:shadow-raised">
      {/* image / category / make-model / spec row / price + CTA, all token-driven */}
    </Link>
  );
}
```

---

## 6. Admin: Filament settings page — DONE

A single Filament page, not a Resource (there's no CRUD list here, just one setting
per region):

```php
// app/Filament/Pages/LayoutSettings.php
class LayoutSettings extends Page
{
    protected static string $view = 'filament.pages.layout-settings';

    public array $selections = [];

    public function mount(): void
    {
        foreach (['vehicleCard', 'fleetLayout', 'reviewDisplay', 'vehicle-gallery', 'checkoutStyle'] as $slot) {
            $this->selections[$slot] = LayoutSetting::where('slot_name', $slot)->value('active_variant_id')
                ?? LayoutVariantRegistry::availableFor($slot)[0]['variantId'];
        }
    }

    public function getFormSchema(): array
    {
        return collect(['vehicleCard', 'fleetLayout', 'reviewDisplay', 'vehicle-gallery', 'checkoutStyle'])->map(fn ($slot) =>
            Select::make("selections.{$slot}")
                ->label(ucfirst(str_replace('-', ' ', $slot)))
                ->options(collect(LayoutVariantRegistry::availableFor($slot))->pluck('label', 'variantId'))
        )->toArray();
    }

    public function save(): void
    {
        foreach ($this->selections as $slot => $variantId) {
            LayoutSetting::updateOrCreate(['slot_name' => $slot], ['active_variant_id' => $variantId]);
        }
        Notification::make()->title('Layout updated')->success()->send();
    }
}
```

This gives a non-technical operator a dropdown per region — "Vehicle Card: [Vertical ▾]",
"Fleet Layout: [Sidebar ▾]", "Reviews: [Compact ▾]", "Gallery: [Carousel ▾]",
"Checkout: [Vertical Stack ▾]" — with the option list itself sourced live from
whatever's registered, including any plugin-contributed variants. *(Implemented —
only slots with at least one registered variant appear; a region with no DB row
falls back to its first registered variant.)*

---

## 7. Extensibility — proving a new variant is cheap

Adding a third vehicle-card option later ("Luxe Card") is:
1. New `.tsx` file implementing `VehicleCardProps` fully
2. One `LayoutVariantRegistry::register('vehicleCard', 'luxe', 'Luxe Card', 'Layout/VehicleCard/Luxe')` call
3. One line added to `layoutComponentRegistry.tsx`
4. It appears in the Filament dropdown automatically — no other code touched

Same shape as adding a plugin, a theme, or a payment gateway — this is deliberately
the fourth application of the exact same "register into a core registry" pattern
used for hooks/events, slots, payment gateways, and layout variants.

---

## 8. Build order for this phase — complete

1. ~~Define the prop contracts in `resources/layout-contracts/`~~ — DONE
2. ~~`LayoutVariantRegistry` + `layout_settings` migration + `LayoutSetting` model~~ — DONE
3. ~~Build two variants per region (Vertical/HorizontalSplit card, Card List/Compact
   reviews, Single Hero/Carousel gallery) — having two from the start is what
   actually proves swapping works, one variant proves nothing~~ — DONE
4. ~~`LayoutSlot` component + `layoutComponentRegistry.tsx` + wiring into
   `HandleInertiaRequests`~~ — DONE
5. ~~Rewire `Vehicles/Index.tsx`, `Vehicles/Show.tsx`, `Home.tsx` to render through
   `LayoutSlot` instead of hardcoded imports~~ — DONE
6. ~~Filament `LayoutSettings` page~~ — DONE
7. Verify (regression — re-run): switch each region's dropdown, confirm the page
   re-renders with the new variant, confirm vehicle data / nav links / booking CTA
   still work regardless of which variant is active, confirm both themes still
   render correctly against both variants of each region (a small matrix, worth
   actually checking: 2 themes × 2 card variants = 4 combinations, glance at all 4)
