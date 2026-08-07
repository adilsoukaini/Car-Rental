<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
