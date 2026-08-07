# Vehicle Reviews

> **Adapted from e-commerce product-reviews.md** — business domain changed to car-rental, architecture preserved.
>
> **Implementation status (as of 2026-08-07):**
> - ✅ **Reviews plugin end to end** — DONE. `plugins/reviews` has the `reviews` migration (rating, title, body, `is_verified_rental`, `is_approved`, unique `(vehicle_id, user_id)`), `App\Models\Review`, `ReviewController::store()`, `VerifiedRentalChecker`, `GetVehicleReviewsPipe` on the `vehicle.reviews` filter, and `ReviewResource` (Staff-level, Approve action, filter by `is_approved`).
> - ✅ **Verified-rental badge** — DONE, and correctly adapted: `VerifiedRentalChecker` requires a real `returned` `Booking` for that vehicle+user (not `paid` as the source's `VerifiedPurchaseChecker` did) — a rental review is only assessable after the rental concluded.
> - ✅ **`ReviewSubmitted` core event** — DONE (`app/Core/Events/ReviewSubmitted.php`), dispatched from `ReviewController::store()`.
> - ✅ **Cross-plugin data access via the filter pipeline** — DONE: `FilterRegistry::applyWithContext('vehicle.reviews', [...], [Vehicle::class => $vehicle])` in `VehicleController::show()`. Only `is_approved = true` reviews cross the boundary.
> - ✅ **Display as a layout-variant region** — DONE, with the variant names adapted to this project: the `reviewDisplay` region (`card-list` → `Widgets/VehicleReviewsCardList` default, `compact` → `Widgets/VehicleReviewsCompact`) renders via `<LayoutSlot name="reviewDisplay" vehicleId={vehicle.id} reviewsData={reviewsData} />` on `Vehicles/Show.tsx`. (The source doc's `StarBreakdown`/`SimpleList` names were not carried over — this project ships its own two variants.)
> - ✅ **Duplicate-review handling** — DONE (pre-check + `UniqueConstraintViolationException` fallback, surfacing a friendly "already reviewed" message).
> - ✅ **Moderation is write-side only** — DONE: `is_approved` starts false; display never filters a forgotten row.

**Goal:** star ratings, written reviews, an aggregate rating shown on the vehicle
page, verified-rental badges, and staff moderation — as a real plugin, using
patterns already proven in this build rather than anything new. Same
core/plugin decoupling lesson as galleries, attributes, and recommendations:
`VehicleController` (in the fleet plugin) cannot import `Plugins\Reviews\Models\Review`
directly, so review data crosses that boundary via
`FilterRegistry::applyWithContext()`, exactly like `vehicle.gallery` already does.

---

## 1. Data — DONE

```php
// plugins/reviews/database/migrations/xxxx_create_reviews_table.php
Schema::create('reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('rating'); // 1-5
    $table->string('title')->nullable();
    $table->text('body');
    $table->boolean('is_verified_rental')->default(false);
    $table->boolean('is_approved')->default(false); // moderation gate — off by default
    $table->timestamps();
    $table->unique(['vehicle_id', 'user_id']); // one review per customer per vehicle
});
```

**Verified rental** is determined at submission time by a real check, not a
checkbox the customer controls:

```php
// plugins/reviews/src/Services/VerifiedRentalChecker.php
public function check(User $user, Vehicle $vehicle): bool
{
    return Booking::where('user_id', $user->id)
        ->where('vehicle_id', $vehicle->id)
        ->where('status', 'returned')   // rental-domain adaptation: a review is
        ->exists();                     // only assessable after the rental concluded
}
```

*(DONE — the real `VerifiedRentalChecker` is exactly this. The source project's
`VerifiedPurchaseChecker` required only `Order.payment_status === 'paid'`; this
project deliberately re-derived it to require a `returned` booking.)*

---

## 2. Event — DONE

`ReviewSubmitted` was anticipated in the very first architecture doc's named-event
list and is now real:

```php
// app/Core/Events/ReviewSubmitted.php
class ReviewSubmitted
{
    use Dispatchable;
    public function __construct(public Review $review) {}
}
```

Dispatched after a review is created — nothing listens to it yet, but it's there
for the same reason every other core event exists: a future plugin (moderation
notifications, a "thank you for reviewing" email) can hook in without touching
the reviews plugin itself.

---

## 3. Submission — DONE

```php
// plugins/reviews/src/Http/Controllers/ReviewController.php
public function store(Request $request, Vehicle $vehicle)
{
    $request->validate(['rating' => 'required|integer|min:1|max:5', 'title' => 'nullable|string|max:255', 'body' => 'required|string|max:2000']);

    $review = Review::create([
        'vehicle_id' => $vehicle->id,
        'user_id' => $request->user()->id,
        'rating' => $request->rating,
        'title' => $request->title,
        'body' => $request->body,
        'is_verified_rental' => app(VerifiedRentalChecker::class)->check($request->user(), $vehicle),
        'is_approved' => false, // always starts unapproved
    ]);

    ReviewSubmitted::dispatch($review);

    return back()->with('success', 'Review submitted — pending approval.');
}
```

The unique constraint on `(vehicle_id, user_id)` means a second submission attempt
fails cleanly at the DB level — surface that as a friendly "you've already reviewed
this vehicle" message, not a raw constraint violation. *(The real controller
pre-checks for an existing review before inserting and keeps the constraint-catch
as a defensive fallback — a strictly better pattern than exception-as-control-flow,
and it avoids Postgres's whole-transaction abort on the duplicate insert.)*

---

## 4. Cross-plugin data access — same filter pattern as gallery — DONE

```php
// plugins/reviews/src/Filters/GetVehicleReviewsPipe.php
class GetVehicleReviewsPipe
{
    public function __construct(private readonly Vehicle $vehicle) {}

    public function handle(array $data, \Closure $next): array
    {
        $reviews = Review::where('vehicle_id', $this->vehicle->id)
            ->where('is_approved', true)
            ->latest()
            ->get();

        return $next([
            'averageRating' => round($reviews->avg('rating') ?? 0, 1),
            'reviewCount' => $reviews->count(),
            'reviews' => $reviews->map(fn ($r) => [
                'id' => $r->id,
                'authorInitials' => strtoupper(substr($r->user->name, 0, 2)),
                'authorName' => $r->user->name,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'isVerifiedRental' => $r->is_verified_rental,
                'createdAt' => $r->created_at->format('M j, Y'),
            ])->toArray(),
        ]);
    }
}
```

```php
// VehicleController::show()
'reviewsData' => FilterRegistry::applyWithContext('vehicle.reviews', ['vehicleId' => $vehicle->id, 'averageRating' => 0.0, 'reviewCount' => 0, 'reviews' => []], [Vehicle::class => $vehicle]),
```

Only `is_approved = true` reviews ever cross this boundary — moderation happens
entirely on the write side, never as a display-time filter a future developer could
forget to apply somewhere else.

---

## 5. Display — a layout variant region, two variants — DONE (names adapted)

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

Two variants, same "one variant proves nothing" rule as every previous region:

- **Card List** (`Widgets/VehicleReviewsCardList`, default) — aggregate rating +
  star count prominently displayed, review cards below with author initials avatar,
  verified badge, rating stars per review (matches the Stitch mockup's style)
- **Compact** (`Widgets/VehicleReviewsCompact`) — a flat list of reviews with
  rating + text, no aggregate breakdown visual — a lighter-weight option for a
  fleet that wants less visual emphasis on reviews

```php
LayoutVariantRegistry::register('reviewDisplay', 'card-list', 'Card List', 'Widgets/VehicleReviewsCardList', 'reviews');
LayoutVariantRegistry::register('reviewDisplay', 'compact', 'Compact', 'Widgets/VehicleReviewsCompact', 'reviews');
```

Wired into `Vehicles/Show.tsx`:

```tsx
<LayoutSlot name="reviewDisplay" vehicleId={vehicle.id} reviewsData={reviewsData} />
```

Appears automatically in `LayoutSettings` via the registry's auto-discovery — no
new admin code needed for the picker itself.

**Both variants follow the same token-only rule as every other component in this
build** — no hardcoded hex colors, px values, or font names. Star ratings, author
initial avatars, verified-rental badges, and card borders all use theme tokens
(`bg-primary`/`text-warning` for stars, `bg-surface`/`border-border` for cards,
`rounded-container`, etc.), so both variants render correctly under any active
theme without modification. Grep both files for hardcoded values before calling
this step done, same check as every previous layout variant.

---

## 6. Admin (Filament) — DONE

```php
// plugins/reviews/src/Filament/Resources/Reviews/ReviewResource.php
use HasMinimumRole;
protected static Role $minimumRole = Role::Staff; // moderation is operational work,
                                                    // same reasoning as BookingResource
```

- List view: vehicle, author, rating, approved status, submitted date
- An **Approve** and **Reject/Delete** row action
- Filter by `is_approved` so staff can quickly find the moderation queue

---

## 7. Build order — essentially complete

1. ~~`reviews` migration (ask before running), `Review` model,
   `VerifiedRentalChecker`~~ — DONE
2. ~~`ReviewSubmitted` event, added to `docs/event-registry.md`~~ — DONE
3. ~~`ReviewController::store()` + route, with the duplicate-review error surfaced
   cleanly~~ — DONE
4. ~~`GetVehicleReviewsPipe` registered into `vehicle.reviews`~~ — DONE
5. ~~`VehicleReviewsProps` contract, two variants (Card List + Compact)~~ — DONE
6. ~~Wire into `Vehicles/Show.tsx`, confirm `reviewDisplay` appears in
   `LayoutSettings` automatically~~ — DONE
7. ~~`ReviewResource` in Filament, Staff-level access, Approve action~~ — DONE
8. Verify (regression — re-run against real data):
   - Submit a review as a customer who has a returned booking for that vehicle —
     confirm `is_verified_rental = true`
   - Submit a review as a customer with no booking for that vehicle — confirm
     `is_verified_rental = false`
   - Confirm an unapproved review does NOT appear on the vehicle page or affect
     the average rating
   - Approve it via Filament, confirm it now appears and the average recalculates
     correctly with real numbers
   - Attempt a second review from the same user on the same vehicle — confirm a
     clean, friendly error, not a raw DB constraint message
   - Switch between Card List and Compact via `LayoutSettings`, confirm both
     render correctly with the same underlying data
