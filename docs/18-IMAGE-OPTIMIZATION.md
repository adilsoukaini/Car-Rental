# Vehicle Image Optimization

> **Status (2026-08-08):** the immediate-win items marked ⚡ below are DONE —
> `loading="lazy"` added to the vehicle gallery components. The rest is the
> strategy to follow when vehicle-image optimization gets built for real. No
> image-processing library is installed yet — `composer.json` has neither
> `intervention/image` nor `spatie/image` (verified by grep). The upload path
> today is the raw `FileUpload` on `VehicleImagesRelationManager`
> (`plugins/vehicle-media`), which stores the original, unoptimized file at
> `vehicle-images/<file>` on the `public` disk.

The single biggest win for a photo-heavy fleet catalog is not one big change —
it's three cheap, layered ones, each independent of the others:

1. **Process images once, at upload time**, into the set of sizes/format the
   UI actually needs (WebP + multiple widths).
2. **Deliver them with native HTML responsive-image markup** (`<picture>` /
   `srcset` / `loading="lazy"`), so each browser downloads only what it needs,
   only when it needs it.
3. **Cache aggressively at the CDN edge**, so repeat visitors don't hit your
   origin at all.

None of these require the others — you can add lazy-loading today, the CDN
next week, and upload-time processing whenever the storage/processing pipeline
is ready. The "immediate win" section at the bottom is the part already done.

---

## 1. On upload — generate what the UI needs, then throw the rest away

Do this once per image, in the plugin that owns the media, at the moment a
file is saved. Laravel's `FileUpload` `->saveUploadedFileUsing()` or a
dedicated service listening after the `VehicleImage` row is created are both
natural homes; the key is that the original is never the thing served to
browsers.

Recommended library: **`intervention/image`** (v3, `intervention/image`
+ `intervention/image-laravel`) or **`spatie/image`**. Either works; pick one
and use it consistently. Both run as pure PHP (GD backend is enough) so no
external ImageMagick dependency is needed on the container.

### Responsive sizes

Generate a fixed set of widths that matches where images actually render in
this app (the gallery hero is `aspect-video`, full column width; thumbnails
are small crops):

| Breakpoint | Width  | Use |
| ---------- | ------ | --- |
| `thumbnail` | 150w | carousel strip thumbnails (`h-16 w-24`) |
| `small`     | 400w | card grids, hero on small/mobile viewports |
| `medium`    | 800w | hero on tablet / default desktop |
| `large`     | 1200w | hero on large desktop displays |

Keep each variant's height proportional to its width (don't pre-crop — the
browser/CSS does the `object-fit`), so `srcset` width selection works
correctly.

### Convert to WebP

Convert every variant to WebP (`image/webp`, `quality 80`). WebP is ~25–35%
smaller than JPEG at the same perceived quality and is supported by every
browser this app targets (Chrome, Firefox, Safari 14+, Edge). Keep the
original format file too only if you need it for re-processing later; for a
static upload path, WebP alone is fine.

### Strip EXIF metadata

Strip EXIF/GPS before storing. The camera's GPS coordinates in a vehicle
photo are a privacy leak (photos can reveal exactly where a fleet's cars are
kept), and the metadata bloat is pure waste. Both libraries strip by default
when re-encoding to WebP, but make it explicit.

### Storage layout

Store each processed variant as a sibling of the current path so the
existing `vehicle_images.path` column keeps working and `Storage::url()`
resolution is unchanged:

```
vehicle-images/<uuid>.webp            <- original-derived: 1200w (largest)
vehicle-images/<uuid>-400w.webp
vehicle-images/<uuid>-800w.webp
vehicle-images/<uuid>-150w.webp
```

Store the canonical path in `vehicle_images.path` as today; derive variant
paths by convention (suffix) rather than adding columns, so no migration is
needed for the first pass. If per-image variant paths ever need to diverge,
that's the point to add a `variants` JSON column instead.

> **Hard Rule 7 note:** if a migration becomes necessary (e.g. a
> `variants` JSON column), show the exact schema and get approval before
> running it against a real database — the standard rule for every migration
> in this project.

---

## 2. On delivery — let the browser ask for exactly what it needs

### `<picture>` with WebP `<source>`

Serve WebP to browsers that support it, fall back to the legacy format for
the ones that don't. The `<picture>` element makes this declarative:

```tsx
<picture>
    <source
        type="image/webp"
        srcSet={`${base}.webp 1200w, ${base}-800w.webp 800w, ${base}-400w.webp 400w`}
        sizes="(min-width: 1024px) 50vw, 100vw"
    />
    <img
        src={`${base}.jpg`}
        srcSet={`${base}.jpg 1200w, ${base}-800w.jpg 800w, ${base}-400w.jpg 400w`}
        sizes="(min-width: 1024px) 50vw, 100vw"
        alt={alt}
        loading="lazy"
    />
</picture>
```

### `srcset` + `sizes` for responsive width selection

`srcset` tells the browser what widths exist; `sizes` tells it how wide the
image will actually render on screen. Together they let the browser pick the
smallest file that still looks sharp — a phone never downloads the 1200w
file. This is the piece that turns "we have 4 sizes" into actual bandwidth
savings; without `sizes`, the browser falls back to guessing.

### `loading="lazy"` for below-the-fold images

Add `loading="lazy"` to every `<img>` that isn't in the initial viewport
(the fleet listing cards, gallery thumbnails). The browser then defers the
network request until the image is about to scroll into view. The hero on the
vehicle detail page is the one candidate that *may* stay eager (`loading="eager"`
or just omit the attribute) since it's usually above the fold — but lazy is
harmless there too, and simplicity wins until profiling says otherwise.

### `decoding="async"` as a cheap bonus

`decoding="async"` lets the browser decode images off the main thread. It's
free to add and pairs naturally with lazy loading; include it on the same
`<img>` elements.

---

## 3. CDN integration — make the origin a rarity

Cloudflare or Google Cloud CDN in front of the app; both are one DNS change
away and both are image-cache-friendly.

- **Serve everything through the CDN** — the storefront assets *and* the
  `public/storage` vehicle images (`/storage/vehicle-images/...`). Do not
  sign URLs for the vehicle images; they're public by design.
- **Long cache headers for images.** Images are immutable in practice (a
  changed photo gets a new path/uuid), so they can be cached very
  aggressively:
  - `Cache-Control: public, max-age=31536000, immutable` (1 year) for the
    image files themselves.
  - 1 hour / `stale-while-revalidate` for the HTML pages, so content edits
    propagate without nuking the cache.
  - If the CDN supports it, enable **WebP negotiation at the edge**
    (Cloudflare Polish or equivalent) as a safety net for any image that
    slips through without a processed WebP variant — it transcodes on the
    fly, buying the same bandwidth win with zero app code.
- **Revalidation after a re-upload.** Because paths change when an image is
  replaced, there's no purge orchestration to write — old paths simply
  expire out of the CDN. This is the same "immutable content" pattern as
  `public/build` hashed assets.

---

## Immediate win — ⚡ done (2026-08-08)

`loading="lazy"` added to every gallery `<img>` in:

- `resources/js/Components/VehicleGallery.tsx` (the single-hero gallery
  variant's hero image)
- `resources/js/Components/VehicleGalleryCarousel.tsx` (the carousel
  variant's hero image **and** its thumbnail strip)

No behavioral change — the images render identically, but the browser now
defers their network requests until they're near the viewport. This was
deliberately the smallest safe first step; `<picture>`/`srcset`/upload-time
processing per sections 1–3 remain to be built, and the gallery components
are where that markup will land.

### Suggested implementation order (after the ⚡ wins)

1. Add `intervention/image` (or `spatie/image`) and generate the four WebP
   sizes at upload in `vehicle-media`. Keep `path` as the largest WebP;
   derive the rest by suffix.
2. Switch `VehicleGallery` / `VehicleGalleryCarousel` (and the fleet-listing
   vehicle cards) to `<picture>` + `srcset` + `sizes`, with the legacy-format
   fallback behind `<source type="image/webp">`.
3. Put Cloudflare (or GCS CDN) in front and add the cache headers above.

Each step is independently shippable and independently verifiable (build
passes, real screenshots with zero console errors — rule 10).
