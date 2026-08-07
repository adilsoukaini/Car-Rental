# Site Identity — Centralized Logo/Brand Management

> **Adapted from e-commerce site-identity-design.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ✅ **`site_identity` table + `App\Models\SiteIdentity` singleton model** — DONE (`2026_08_07_000000_create_site_identity_table.php`).
> - ✅ **`siteIdentity` shared prop** — DONE. `HandleInertiaRequests::share()` resolves it via a private `siteIdentity()` method with a `config()` fallback when the row/column is null.
> - ✅ **`SiteLogo` component with text fallback** — DONE (`resources/js/Components/SiteLogo.tsx`), consumed by `PublicLayout`, `AuthenticatedLayout`, and `GuestLayout`.
> - ✅ **Favicon wiring** — DONE in `resources/views/app.blade.php` (reads `SiteIdentity::first()->favicon_path`, renders the `<link rel="icon">` server-side in the initial HTML).
> - ✅ **`SiteIdentitySettings` Filament page** — DONE (`app/Filament/Pages/SiteIdentitySettings.php`), `Admin`-only via `HasMinimumRole`, with site-name text input + primary logo and favicon `FileUpload`s.
> - ❌ **Dark-theme logo (`logo_dark_path`)** — NOT DONE, deliberately deferred. `SiteIdentitySettings` documents this explicitly: the storefront `SiteLogo` doesn't consume a dark variant yet, so the column was not built.
> - ❌ **Filament admin panel branding wired to site identity** — NOT DONE. `AdminPanelProvider` still hardcodes `->brandName('Car Rental')` instead of reading `SiteIdentity::current()['siteName']`.
> - ❌ **Dark-vs-light logo resolution via `ContrastChecker::luminance()`** — NOT DONE (blocked on the dark logo column existing).

**Goal:** one admin screen controls the logo (and related brand assets) for the
entire site, replacing the current situation where the storefront header, the
auth layout, and the footer each independently reference brand text. This is a
settings/config model, not a `LayoutVariantRegistry` region — a logo is "what is
my brand" (one source of truth), not "which visual style" (a swappable choice
between designs). Don't conflate the two patterns.

---

## 1. Data — a singleton settings row, same shape as other admin settings — DONE

```php
// database/migrations/xxxx_create_site_identity_table.php
Schema::create('site_identity', function (Blueprint $table) {
    $table->id();
    $table->string('site_name')->default('Car Rental');
    $table->string('logo_path')->nullable();       // primary logo, for light backgrounds
    $table->string('logo_dark_path')->nullable();   // for dark-background themes — NOT YET BUILT
    $table->string('favicon_path')->nullable();
    $table->timestamps();
});
```

Single row (id=1), same "one settings record" pattern as everything else in this
build that isn't per-item data.

```php
// app/Core/Support/SiteIdentity.php
class SiteIdentity
{
    public static function current(): array
    {
        $record = \App\Models\SiteIdentity::first();
        return [
            'siteName' => $record?->site_name ?? config('app.name', 'Car Rental'),
            'logoUrl' => $record?->logo_path ? Storage::url($record->logo_path) : null,
            'logoDarkUrl' => $record?->logo_dark_path ? Storage::url($record->logo_dark_path) : null,
            'faviconUrl' => $record?->favicon_path ? Storage::url($record->favicon_path) : null,
        ];
    }
}
```

*(Implemented as `HandleInertiaRequests::siteIdentity()`, which does this
resolution with the `config()` fallback.)*

---

## 2. Which logo shows — light vs. dark, with a text fallback — NOT YET IMPLEMENTED

Rather than the frontend guessing which logo to show, resolve it based on whether
the *active theme* is a dark theme. A simple, sufficient signal: check the active
theme's `color.background` luminance (you already have a luminance/contrast
formula from `ContrastChecker` — reuse it, don't write a second one).

```php
// wherever themeData is resolved (HandleInertiaRequests, alongside ThemeManager::resolveActive())
$isDarkTheme = ContrastChecker::luminance($themeData['color']['background']) < 0.5;
$logo = $isDarkTheme && $identity['logoDarkUrl'] ? $identity['logoDarkUrl'] : $identity['logoUrl'];
```

**Text fallback**: if no logo image is uploaded at all (`logoUrl` is null), render
`siteName` as text using `font-display` and `text-primary` — this is close to what
every header already does today, so a fresh install with nothing uploaded yet
looks exactly as it does now, not broken. *(The text fallback is implemented in
`SiteLogo.tsx`; the light-vs-dark image choice is not, because `logo_dark_path`
doesn't exist yet.)*

---

## 3. Wiring — shared once, consumed everywhere — DONE

```php
// HandleInertiaRequests::share() — alongside themeData, activeLayoutVariants
'siteIdentity' => SiteIdentity::current(),
```

Every place a logo currently renders switches from its own hardcoded text/image to
reading this shared prop:

```tsx
// a small shared component, used by the storefront header, GuestLayout, and AuthenticatedLayout
export function SiteLogo({ className }: { className?: string }) {
  const { siteIdentity } = usePage().props as any;
  if (siteIdentity.logoUrl) {
    return <img src={siteIdentity.logoUrl} alt={siteIdentity.siteName} className={className} />;
  }
  return <span className={`font-display text-primary font-bold ${className}`}>{siteIdentity.siteName}</span>;
}
```

Replace the hardcoded logo markup in **PublicLayout**, **GuestLayout**, and
**AuthenticatedLayout** with `<SiteLogo />`. *(DONE — all three layouts consume
`SiteLogo`.)* This is the same kind of "fix the shared component, every consumer
updates at once" fix as the scaffold theme retrofit — a handful of files, not a
site-wide rewrite.

---

## 4. Favicon — DONE

```blade
{{-- app.blade.php --}}
<link rel="icon" href="{{ $faviconUrl ?? '/favicon.ico' }}">
```

Passed from the same `SiteIdentity` row, rendered server-side in the Blade shell
(favicons aren't something Inertia/React can swap at runtime the way CSS variables
can — this one needs to be in the initial HTML response). *(Implemented — reads
`SiteIdentity::first()->favicon_path` and renders the `<link>` conditionally.)*

---

## 5. Filament admin panel branding — NOT YET IMPLEMENTED

Filament supports customizing its own panel branding (`brandName`, `brandLogo`)
in the panel provider config. Wire it to the same `SiteIdentity` data so your
admin dashboard shows your actual brand, not the hardcoded default:

```php
// app/Providers/Filament/AdminPanelProvider.php
->brandName(fn () => SiteIdentity::current()['siteName'])
->brandLogo(fn () => SiteIdentity::current()['logoUrl'])
```

*(Currently `AdminPanelProvider` hardcodes `->brandName('Car Rental')`.)*

---

## 6. Admin (Filament) — DONE (site name, primary logo, favicon)

A single settings page (`SiteIdentitySettings`, same shape as `LayoutSettings`):
site name text field, primary logo upload, favicon upload. `HasMinimumRole` set to
`Admin` — brand identity is structural/site-wide, same reasoning as
`ThemeResource`. *(Implemented; the dark-theme logo upload is deliberately omitted
until a consumer for it exists.)*

---

## 7. Build order

1. ~~`site_identity` migration (ask before running), `SiteIdentity` model,
   `HandleInertiaRequests` sharing~~ — DONE
2. ~~Share `siteIdentity` from `HandleInertiaRequests`~~ — DONE
3. ~~`SiteLogo` shared component (section 3), with the text fallback~~ — DONE
4. ~~Replace hardcoded logo markup in PublicLayout/GuestLayout/AuthenticatedLayout~~ — DONE
5. ~~Favicon wiring in `app.blade.php`~~ — DONE
6. Filament panel branding wired to the same settings (section 5) — NOT DONE
7. ~~`SiteIdentitySettings` Filament page, Admin-only~~ — DONE
8. Optional future: `logo_dark_path` column + dark-theme logo upload + the
   light-vs-dark resolution (section 2) — NOT DONE
9. Verify:
   - Fresh install, nothing uploaded — every header/auth page shows the text
     fallback correctly, nothing broken
   - Upload a primary logo, confirm it appears in ALL locations (PublicLayout,
     AuthenticatedLayout, GuestLayout) with zero code change, zero rebuild
   - Upload a favicon, confirm the browser tab updates
   - Confirm the Filament admin panel itself shows the updated brand name/logo
     (once section 5 is built)
   - Switch between the storefront header variants, confirm the logo stays
     consistent across all of them (this is the actual point — one change, every
     consumer reflects it)
