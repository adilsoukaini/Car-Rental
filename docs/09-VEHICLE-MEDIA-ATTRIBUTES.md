# Vehicle Images & Custom Attributes

> **Adapted from e-commerce product-media-attributes.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ✅ **Vehicle images (upload, reorder, set primary)** — DONE. The `vehicle-media` plugin exists (`plugins/vehicle-media`) with the `vehicle_images` table, `GetVehicleGalleryPipe` registered on the `vehicle.gallery` filter, `EagerLoadPrimaryImagePipe` for batch-loading (rule 8), and `VehicleImagesRelationManager` managing images directly on `VehicleResource`. `VehicleController::show()` resolves the gallery via `FilterRegistry::applyWithContext('vehicle.gallery', [], [Vehicle::class => $vehicle])` and the detail page renders it.
> - ✅ **The filter-pipeline decoupling this doc calls for** — DONE. The `FilterRegistry::applyWithContext()` adjustment this doc flags as "a real extension to the existing filter mechanism" already exists in this project and is what `vehicle.gallery` uses — no extra kernel change is needed.
> - ❌ **Vehicle attributes / EAV system** — NOT DONE. No `vehicle-attributes` plugin, no `vehicle_attribute_definitions`/`vehicle_attribute_values` tables, no `AttributeValueCaster`, no `AttributeDefinitionResource`, no dynamic form section on `VehicleResource`. This is the only remaining piece of this doc.
> - ✅ **Gallery layout variants (SingleHero/Carousel) via `LayoutVariantRegistry`** — DONE. `vehicle-gallery` is registered (`single-hero` → `Components/VehicleGallery`, `carousel` → `Components/VehicleGalleryCarousel`), both components exist, and `Vehicles/Show.tsx` renders the region via `<LayoutSlot name="vehicle-gallery" images={galleryImages} />`. An admin switches it from `LayoutSettings`.
> - ✅ **Storage backend swapping** — DONE by Laravel's own filesystem abstraction, exactly as this doc says; `vehicle_images.path` stores a disk-relative path resolved via `Storage::url()`.

**Goal:** two related but separate plugins. `vehicle-media` manages a vehicle's image
gallery (upload, reorder, set primary) from Filament. `vehicle-attributes` lets an
admin define an unlimited number of custom vehicle fields (a spec table, essentially
— "Fuel Type", "Transmission", "Seat Count", "Air Conditioning," whatever a given
fleet needs) without any code change per field. Both the gallery's and the
attributes' *display shape* are swappable via the existing `LayoutVariantRegistry`
— same system, two new regions.

**A lesson from the fleet and reviews phases applied proactively here, not
discovered again:** `VehicleController` is in the fleet plugin. Gallery images and
attribute values live in plugin-owned tables
(`Plugins\VehicleMedia\Models\VehicleImage`,
`Plugins\VehicleAttributes\Models\VehicleAttributeValue`). Neither plugin's
controller can import the other plugin's classes directly — the same Hard Rule #1
that put `CancellationPolicyRequest` in core. Rather than
inventing a third one-off DTO pattern, this doc uses the **filter pipeline** for
this instead: core (or the fleet plugin) asks
`FilterRegistry::applyWithContext('vehicle.gallery', [], [Vehicle::class => $vehicle])`
and
`FilterRegistry::applyWithContext('vehicle.attributes', [], [Vehicle::class => $vehicle])`,
and each plugin registers a pipe that resolves its own data and returns a plain
array. Neither the fleet plugin nor core ever references either plugin's models.

---

## 1. vehicle-media plugin

```php
// plugins/vehicle-media/database/migrations/xxxx_create_vehicle_images_table.php
Schema::create('vehicle_images', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
    $table->string('path');            // Laravel filesystem disk path, not a raw URL
    $table->string('alt_text')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('is_primary')->default(false);
    $table->timestamps();
});
```

> **Already implemented in `plugins/vehicle-media`** — the real migration adds an
> index on `('vehicle_id', 'is_primary')` but is otherwise exactly this shape.

**Storage backend swapping is already solved — don't build a new registry for it.**
Laravel's own filesystem disk abstraction (`config/filesystems.php`) already lets you
swap local disk for S3/R2/whatever per deployment with a config change, not a code
change. `vehicle_images.path` stores a disk-relative path; resolving it to an actual
URL happens via `Storage::url($path)` wherever it's displayed. This is a case where
the platform's existing tooling already provides the extensibility point — inventing
a `StorageDriver` interface here would be solving an already-solved problem.

```php
// plugins/vehicle-media/src/VehicleMediaServiceProvider.php
public function boot()
{
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    FilterRegistry::register('vehicle.gallery', GetVehicleGalleryPipe::class);

    LayoutVariantRegistry::register('vehicle-gallery', 'single-hero', 'Single Hero Image', 'Layout/Gallery/SingleHero');
    LayoutVariantRegistry::register('vehicle-gallery', 'carousel', 'Carousel', 'Layout/Gallery/Carousel');

    Filament::registerResources([ /* images managed as a relation manager on VehicleResource, not a standalone resource */ ]);
}
```

```php
// plugins/vehicle-media/src/Pipes/GetVehicleGalleryPipe.php
class GetVehicleGalleryPipe
{
    public function handle($images, \Closure $next, Vehicle $vehicle)
    {
        $resolved = VehicleImage::where('vehicle_id', $vehicle->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($img) => ['url' => Storage::url($img->path), 'altText' => $img->alt_text, 'isPrimary' => $img->is_primary])
            ->toArray();
        return $next($resolved);
    }
}
```

(Note: this project's `FilterRegistry` already supports passing the `$vehicle` as
extra context via `applyWithContext()` — the pipeline adjustment this note asks for
was solved in the reviews phase and is what `GetVehicleGalleryPipe` actually uses.
`GetVehicleGalleryPipe` here is a faithful copy of the real one.)

**Filament:** images are managed as a relation manager or repeater directly on
`VehicleResource` (upload, drag to reorder → updates `sort_order`, toggle "primary").
Setting a new primary image should use the same transactional
deactivate-then-activate pattern as `ThemeManager::activate()` — only one primary
image per vehicle. **Already implemented** in
`plugins/vehicle-media/src/Filament/RelationManagers/VehicleImagesRelationManager.php`.

---

## 2. vehicle-attributes plugin (the EAV system) — NOT YET IMPLEMENTED

```php
// plugins/vehicle-attributes/database/migrations/xxxx_create_vehicle_attribute_tables.php
Schema::create('vehicle_attribute_definitions', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();      // slug-like, e.g. 'transmission'
    $table->string('label');               // 'Transmission'
    $table->string('type');                 // 'text' | 'number' | 'textarea' | 'boolean' | 'select'
    $table->json('options')->nullable();     // for 'select' type: [{value, label}, ...]
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});

Schema::create('vehicle_attribute_values', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
    $table->foreignId('attribute_definition_id')->constrained()->cascadeOnDelete();
    $table->text('value')->nullable();       // stored as text, cast per-type at read time
    $table->unique(['vehicle_id', 'attribute_definition_id']);
    $table->timestamps();
});
```

**Deliberately just 5 built-in types, not a type registry — same discipline as
deferring `shipping.cost` in the source project's Phase 9.** The actual ask here is
"let an admin add unlimited *fields*," which the EAV pattern already delivers in
full. Adding an *extensible attribute type system* (so a plugin could register a 6th
type like "color swatch" or "file upload") is a different, larger feature that isn't
needed yet — build it if and when a real 6th type is actually wanted, not
speculatively.

```php
// plugins/vehicle-attributes/src/Services/AttributeValueCaster.php
class AttributeValueCaster
{
    public static function cast(string $type, ?string $rawValue): mixed
    {
        return match ($type) {
            'number' => $rawValue !== null ? (float) $rawValue : null,
            'boolean' => $rawValue === '1',
            default => $rawValue, // text, textarea, select all display as their stored string
        };
    }
}
```

```php
// plugins/vehicle-attributes/src/Pipes/GetVehicleAttributesPipe.php
class GetVehicleAttributesPipe
{
    public function handle($attributes, \Closure $next, Vehicle $vehicle)
    {
        $resolved = VehicleAttributeValue::where('vehicle_id', $vehicle->id)
            ->whereNotNull('value')
            ->with('definition')
            ->get()
            ->sortBy('definition.sort_order')
            ->map(fn ($v) => [
                'label' => $v->definition->label,
                'value' => AttributeValueCaster::cast($v->definition->type, $v->value),
                'type' => $v->definition->type,
            ])
            ->values()
            ->toArray();
        return $next($resolved);
    }
}
```

**Filament — two admin surfaces:**
1. `AttributeDefinitionResource` — plain CRUD where an admin adds/edits/removes
   field definitions (key, label, type, options for select, sort order). This is
   the "add whatever number we want" part — pure data, no code per field.
2. On `VehicleResource`'s edit form, a dynamically generated section: iterate every
   `VehicleAttributeDefinition`, render the matching Filament form component
   (`TextInput` for text/number, `Textarea`, `Toggle` for boolean, `Select` with
   `options` for select), bound to that vehicle's `VehicleAttributeValue` row. This
   is the same "read a registry/DB definitions, render fields generically" pattern
   already used for the customer-facing preferences form in the source project's
   Phase 12 — same idea, Filament-side this time.

---

## 3. Display — two new layout variant regions, reusing the existing registry — NOT YET IMPLEMENTED

```typescript
// resources/layout-contracts/GalleryProps.ts
export interface GalleryProps {
  images: { url: string; altText: string | null; isPrimary: boolean }[];
}
```

```typescript
// resources/layout-contracts/AttributesDisplayProps.ts
export interface AttributesDisplayProps {
  attributes: { label: string; value: string | number | boolean; type: string }[];
}
```

Two variants each, same "one variant proves nothing" rule as every other region:
- `vehicle-gallery`: `SingleHero` (just the primary image, large) and `Carousel`
  (all images, swipeable/clickable thumbnails)
- `vehicle-attributes-display`: `PlainList` (label: value pairs, simple) and
  `SpecTable` (a bordered table, more formal/catalog-like)

Both render via `LayoutSlot`, wired into the vehicle detail page:

```tsx
// resources/js/Pages/Vehicles/Show.tsx
<LayoutSlot name="vehicle-gallery" images={vehicle.gallery} />
{/* ...title, daily rate, description, existing slots... */}
<LayoutSlot name="vehicle-attributes-display" attributes={vehicle.attributes} />
```

```php
// plugins/fleet-management/src/Http/Controllers/VehicleController.php — show()
return Inertia::render('Vehicles/Show', [
    'vehicle' => array_merge($vehicle->toArray(), [
        'gallery' => FilterRegistry::applyWithContext('vehicle.gallery', [], [Vehicle::class => $vehicle]),
        'attributes' => FilterRegistry::applyWithContext('vehicle.attributes', [], [Vehicle::class => $vehicle]),
    ]),
    'slots' => [ /* existing slots unchanged */ ],
]);
```

Note: `VehicleController` lives in the **fleet** plugin, not core — same Hard
Rule applies between fleet and vehicle-media/vehicle-attributes as it does for
core: fleet cannot import `Plugins\VehicleMedia\...` or
`Plugins\VehicleAttributes\...` directly either. The filter-based resolution
described above is exactly what avoids that coupling regardless of which plugin
is asking.

---

## 4. Build order

1. ~~`vehicle-media` plugin: migration, `VehicleImage` model, `vehicle.gallery` pipe,
   `SingleHero`/`Carousel` variants, Filament image management on `VehicleResource`~~ —
   **DONE** (image management, the pipe, eager-loading, AND the two gallery
   layout-variant components all ship). Ask before running any new migration.
2. `vehicle-attributes` plugin: both migrations, `VehicleAttributeDefinition` +
   `VehicleAttributeValue` models, `AttributeValueCaster`, `vehicle.attributes` pipe,
   `PlainList`/`SpecTable` variants, `AttributeDefinitionResource`, dynamic form
   section on `VehicleResource` — **NOT STARTED** — ask before running the migrations
3. Confirm/implement whatever `FilterRegistry::applyWithContext()` adjustment is
   needed to pass `$vehicle` as context through the pipeline (flagged in section 1) —
   **already solved**, `applyWithContext()` exists and is used by `vehicle.gallery`
4. ~~Wire `Vehicles/Show.tsx` to render both new layout variant regions~~ — **DONE**
   (`<LayoutSlot name="vehicle-gallery" ... />` renders on the detail page)
5. Verify: upload 3 images to a vehicle, reorder them, set a different primary,
   confirm both gallery variants render correctly; define 3 custom attributes
   (one of each: text, number, select) on a vehicle, confirm both attribute-display
   variants render correctly and values are cast/formatted per type; confirm
   deleting a vehicle cascades and removes its images/attribute values (foreign key
   cascade, but verify it actually happens, don't just assume the migration is correct)
