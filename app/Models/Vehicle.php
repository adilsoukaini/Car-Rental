<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Laravel\Scout\Searchable;

/**
 * Relations registered dynamically by plugins via resolveRelationUsing():
 * - images(): HasMany (vehicle-media plugin)
 * - primaryImage(): HasOne (vehicle-media plugin)
 *
 * @method \Illuminate\Database\Eloquent\Relations\HasMany images()
 * @method \Illuminate\Database\Eloquent\Relations\HasOne primaryImage()
 */
#[Fillable([
    'make', 'model', 'year', 'category', 'license_plate', 'daily_rate',
    'seat_count', 'transmission_type', 'fuel_type', 'mileage', 'status',
    'location_id', 'photos', 'metadata',
])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    use Searchable;

    /**
     * The attributes the Scout database driver searches over.
     *
     * Scout's database engine performs a case-insensitive ILIKE/LIKE match
     * against exactly these columns (the keys are the real `vehicles` table
     * columns). Keep this minimal — a suggestion endpoint wants lightweight
     * matches on the human-facing identity of the car, not every spec field.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'make' => $this->make,
            'model' => $this->model,
            'category' => $this->category,
            'year' => $this->year,
        ];
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'daily_rate' => 'decimal:2',
            'seat_count' => 'integer',
            'mileage' => 'integer',
            'photos' => 'array',
            'metadata' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Reviews are a core model too (same precedent as DriverVerification:
     * the reviews plugin owns the migration/logic/Filament resource, but
     * both models live in App\Models). Defined here so any fleet query can
     * aggregate review data without referencing the plugin's namespace.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Batch-load a review summary (approved count + average rating) for a
     * whole fleet listing in ONE query — no per-card N+1 (rule 8). Both
     * aggregates are constrained to approved reviews, matching the detail
     * page's GetVehicleReviewsPipe exactly (an unapproved review is
     * invisible to everyone except staff).
     *
     * Guarded by Schema::hasTable so the query never hard-crashes when the
     * reviews plugin is disabled and its table doesn't exist (same "core
     * must not hard-crash over one optional feature" principle as the
     * driver-verification middleware guard). Degrades to no summary —
     * the cards hide the snippet when reviews_count is absent.
     */
    public function scopeWithReviewSummary(Builder $query): Builder
    {
        if (Schema::hasTable('reviews')) {
            $query->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
                ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating');
        }

        return $query;
    }
}
