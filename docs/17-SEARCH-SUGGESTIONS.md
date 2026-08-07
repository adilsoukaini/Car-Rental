# Search Suggestions — Extensible Across Content Types and Search Boxes

> **Adapted from e-commerce search-suggestions.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ❌ **`SearchProvider` interface / `SearchResult` DTO / `SearchProviderRegistry`** — NOT DONE. None exist in `app/Core/`.
> - ❌ **`SearchController::suggestions()` endpoint** — NOT DONE. There is intentionally **no backend search endpoint at all** — the current `SearchBox` component is frontend-only.
> - ❌ **`useSearchSuggestions` hook (type-parameterized, debounced)** — NOT DONE.
> - ⚠️ **`SearchBox` component — PARTIAL.** `resources/js/Components/SearchBox.tsx` exists and is a debounced search input (emits `onSearch(query)` 200ms after typing stops, token-styled), but it has no suggestions dropdown, no `type`/`renderResult` props, and no backend fetch — the parent owns the query. The suggestion mechanism this doc specifies is not built.
> - ⚠️ **Meilisearch dependency** — NOT DONE in this project. The source doc's `Product::search()` relies on a Meilisearch (or similar full-text) integration this project does not have. The registry contract below is unchanged, but the `vehicles` provider's `search()` implementation would need a real search backend (or an Eloquent `LIKE` fallback) built first. **The exact procedure for switching Scout from its current `database` engine to Meilisearch once a server is available is documented in [Section 5](#5-switching-the-search-engine-from-database-to-meilisearch-when-a-server-is-available) — nothing in this doc installs or configures anything; it only records the steps.**

**Goal:** the header search box is the first tenant, not the only one. A future
dedicated `/search` page, a staff booking-lookup box in the admin area, or a plugin
wanting to make its own content searchable (help articles, blog posts) should all
be able to reuse the same suggestion mechanism — plugging in a new **content type**
via a registry, and reusing the same **UI component** wherever a search box is
needed. Same shape as `PaymentGatewayRegistry`/`LayoutVariantRegistry`:
a core contract, a registry, and a generic result shape everything conforms to.

---

## 1. Core contract — what gets searched is swappable — NOT YET IMPLEMENTED

```php
// app/Core/Contracts/SearchProvider.php
interface SearchProvider
{
    public function id(): string;             // 'vehicles' | 'categories' | ...
    public function label(): string;           // shown if a search box lets a user pick scope
    public function search(string $query, int $limit = 5): array; // see SearchResult shape below
}
```

```php
// app/Core/DTOs/SearchResult.php — generic, works for any content type
class SearchResult
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $title,
        public readonly ?string $subtitle = null,   // daily rate for vehicles, breadcrumb for categories, etc.
        public readonly ?string $imageUrl = null,
        public readonly string $url,                 // where clicking the result navigates
    ) {}
}
```

```php
// app/Core/Support/SearchProviderRegistry.php
// register($provider, $pluginSlug), get($id), all() — same shape as every other
// registry in this build
```

Core registers the vehicles provider (today's only real one — reusing whatever
search backend exists, or an Eloquent fallback until one does):

```php
// app/Providers/AppServiceProvider.php (or a dedicated SearchServiceProvider)
class VehicleSearchProvider implements SearchProvider
{
    public function id(): string { return 'vehicles'; }
    public function label(): string { return 'Vehicles'; }
    public function search(string $query, int $limit = 5): array
    {
        return Vehicle::where('status', 'available')
            ->where(fn ($q) => $q->where('make', 'like', "%{$query}%")->orWhere('model', 'like', "%{$query}%"))
            ->limit($limit)
            ->get()
            ->map(fn ($v) => new SearchResult(
                id: $v->id,
                title: "{$v->make} {$v->model}",
                subtitle: number_format((float) $v->daily_rate, 2) . ' MAD / day',
                imageUrl: $v->primary_image?->url ?? null,
                url: "/vehicles/{$v->id}",
            ))
            ->toArray();
    }
}

SearchProviderRegistry::register(new VehicleSearchProvider(), 'core');
```

*(Daily rate is a decimal on `vehicles` (`daily_rate`, e.g. `350.00`), not a cent
integer — the source doc's `price_cents / 100` is not needed.)*

A future plugin (e.g. a help-center plugin) registers its own provider the same way,
from its own Service Provider, without touching this file:

```php
SearchProviderRegistry::register(new HelpArticleSearchProvider(), 'help-center');
```

---

## 2. One endpoint, parameterized by which provider to query — NOT YET IMPLEMENTED

```php
// app/Http/Controllers/SearchController.php
public function suggestions(Request $request)
{
    $query = $request->query('q', '');
    $type = $request->query('type', 'vehicles'); // defaults to vehicles — the header's use case

    if (mb_strlen($query) < 2) return response()->json(['results' => []]);

    $provider = SearchProviderRegistry::get($type);
    if (!$provider) return response()->json(['results' => []], 404);

    return response()->json(['results' => $provider->search($query, 5)]);
}
```

Every consumer hits the same `/search/suggestions?type=X&q=...` endpoint — a new
search box for a new content type never needs a new route, just a new registered
provider and a `type` value to pass.

---

## 3. One reusable frontend component, parameterized the same way — PARTIALLY IMPLEMENTED

```typescript
// resources/js/hooks/useSearchSuggestions.ts — NOT YET IMPLEMENTED
export function useSearchSuggestions(query: string, type: string = 'vehicles') {
  // debounce logic, now includes &type=${type} in the fetch URL
}
```

```tsx
// resources/js/Components/SearchBox.tsx — currently frontend-only; the suggestion
// dropdown and type/result props below are NOT yet built
interface SearchBoxProps {
  type?: string;                                  // which SearchProvider to query, default 'vehicles'
  onSubmitFullSearch: (query: string) => void;     // "see all results" / Enter with no highlight
  renderResult?: (result: SearchResult) => React.ReactNode; // optional custom rendering
  placeholder?: string;
}

export function SearchBox({ type = 'vehicles', onSubmitFullSearch, renderResult, placeholder }: SearchBoxProps) {
  const [query, setQuery] = useState('');
  const { results } = useSearchSuggestions(query, type);
  // keyboard nav (arrow/enter/escape) lives here, once, shared by every consumer

  const defaultRender = (r: SearchResult) => (
    <Link href={r.url}>
      {r.imageUrl && <img src={r.imageUrl} className="w-8 h-8" />}
      <span>{r.title}</span>
      {r.subtitle && <span className="text-textMuted">{r.subtitle}</span>}
    </Link>
  );

  return (
    <div className="relative">
      <input value={query} onChange={e => setQuery(e.target.value)} placeholder={placeholder} />
      {results.length > 0 && (
        <ul className="absolute bg-surface border border-border rounded-container shadow-raised">
          {results.map(r => <li key={r.id}>{(renderResult ?? defaultRender)(r)}</li>)}
          <li><button onClick={() => onSubmitFullSearch(query)}>See all results for "{query}"</button></li>
        </ul>
      )}
    </div>
  );
}
```

Both header states use `<SearchBox onSubmitFullSearch={onSearchSubmit} />` with
zero custom rendering needed (the default render covers vehicles fine). A
future admin quick-lookup or a help-center search box uses
`<SearchBox type="help-articles" renderResult={customRenderer} />` — same component,
different provider, optionally different result rendering. No duplicated
debounce/keyboard-nav logic anywhere.

*(The current `SearchBox` covers the debounced-input half; the suggestions
dropdown, `type` routing, and backend fetch are the missing pieces.)*

---

## 4. Build order

1. `SearchProvider` interface, `SearchResult` DTO, `SearchProviderRegistry`,
   `VehicleSearchProvider` registered as `'vehicles'` (decide the search backend
   first: Meilisearch integration or an Eloquent `LIKE` fallback)
2. `SearchController::suggestions()` reading the `type` param
3. `useSearchSuggestions` hook (type-parameterized) + extend `SearchBox` with the
   suggestions dropdown / `type` / `renderResult` props
4. Wire the extended `<SearchBox>` into the storefront header — confirm it works
5. Verify: header search works (partial match, 2-char minimum, available-only);
   confirm the `type` param is actually being read and routed correctly by
   manually hitting `/search/suggestions?type=vehicles&q=...` and
   `?type=doesnotexist&q=...` (should return empty/404, not error); confirm no
   component hardcodes the `/search/suggestions` URL directly — everything goes
   through `SearchBox`/the hook

---

## 5. Switching the search engine from `database` to Meilisearch (when a server is available)

> **Current state (as of 2026-08-07):** Scout runs on its `database` engine
> (`SCOUT_DRIVER=database` in `.env`); `meilisearch/meilisearch-php` is **not**
> installed, and no Meilisearch server is running in this environment (checked:
> no Docker container, no `meilisearch` binary, nothing listening on `:7700`).
> The steps below are the exact switch procedure to run once a real Meilisearch
> server exists. **Nothing below has been installed or configured by this
> document** — it records the procedure so the switch is a checklist, not a
> re-discovery.

### Why switch

The `database` driver issues a LIKE/ILIKE over the columns in
`Vehicle::toSearchableArray()` per search — correct and zero-infrastructure, but
it can't rank results, can't do typo tolerance, and doesn't scale. Meilisearch
is the ranked, typo-tolerant full-text engine the search-suggestion feature
should ultimately sit on. The switch is invisible to callers: everything already
goes through `Vehicle::search()`, and Scout swaps the underlying engine.

### The switch, step by step

1. **Install the PHP client** — the only missing package (`laravel/scout` is
   already installed, see `composer.json`):

   ```bash
   composer require meilisearch/meilisearch-php
   ```

2. **Point Scout at Meilisearch** in `.env`:

   ```bash
   SCOUT_DRIVER=meilisearch
   MEILISEARCH_HOST=http://localhost:7700
   MEILISEARCH_KEY=            # the server's master key, if one is set
   ```

   `config/scout.php` already ships a `meilisearch` block that reads these exact
   env vars (`host` defaults to `http://localhost:7700`, `key` to `MEILISEARCH_KEY`)
   — so no `config/scout.php` edit is *required* to switch engines.

3. **Make `toSearchableArray()` Meilisearch-ready.** The `database` driver only
   needs the columns it LIKE-matches against; Meilisearch needs the same data
   **plus every attribute you want to filter or constrain on** exposed as a
   filterable attribute. Today `Vehicle::toSearchableArray()` returns only
   `id, make, model, category, year` — `SearchController::suggestions()` also
   calls `->where('status', 'available')`, which under Meilisearch requires
   `status` to be present and filterable or the search fails. Add what search
   actually needs, e.g.:

   ```php
   public function toSearchableArray(): array
   {
       return [
           'id' => $this->id,
           'make' => $this->make,
           'model' => $this->model,
           'category' => $this->category,
           'year' => $this->year,
           'status' => $this->status,
           'daily_rate' => (float) $this->daily_rate,
       ];
   }
   ```

4. **Configure index settings** (recommended) in `config/scout.php`'s
   `meilisearch.index-settings`, then apply with `php artisan scout:sync-index-settings`:

   ```php
   'meilisearch' => [
       'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
       'key' => env('MEILISEARCH_KEY'),
       'index-settings' => [
           'vehicles' => [
               'searchableAttributes' => ['make', 'model', 'category', 'year'],
               'filterableAttributes' => ['status', 'category', 'daily_rate'],
           ],
       ],
   ],
   ```

5. **Re-import existing rows.** The `database` engine reads the table live; a
   Meilisearch index needs an explicit push (new rows are pushed automatically
   by the `Searchable` trait's model-event hooks once the engine is live):

   ```bash
   php artisan scout:flush "App\Models\Vehicle"
   php artisan scout:import "App\Models\Vehicle"
   ```

6. **Verify against the real server** (not just "the import said done"):

   ```bash
   curl -s http://localhost:7700/health
   # {"status":"available"}
   curl -s 'http://localhost:7700/indexes/vehicles/documents/search' \
     -H 'Content-Type: application/json' \
     -H "Authorization: Bearer $MEILISEARCH_KEY" \
     -d '{"q":"toyo"}'          # ranked hits for Toyota
   ```

### What changes (and what doesn't) after the switch

- **`SearchController::suggestions()` keeps working unchanged** — it calls
  `Vehicle::search($query)->where('status', 'available')->take(5)->get()`, and
  Scout presents the same builder API over Meilisearch. The `status` filter is
  the one thing that must be made filterable (steps 3–4) or it stops working.
- **`tests/Feature/SearchControllerTest.php`'s docblock becomes wrong.** It
  currently documents the `database`-engine behavior (LIKE against the live
  table, no import needed). A Meilisearch-backed automated suite needs a running
  server (or a mocked engine) plus an import step — that's a test-harness change
  to make *at* switch time, not part of this doc.
- **The fleet listing's `?search=` filter does NOT use Scout at all.** See
  `VehicleController::index()` — it's a plain `LOWER(make/model) LIKE`, kept out
  of the filter registry deliberately. The engine switch has zero effect on it.
- **Scout is only wired to `App\Models\Vehicle`** (the `Searchable` trait).
  No other model is searchable today; nothing else needs re-importing.
