<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'make', 'model', 'year', 'category', 'license_plate', 'daily_rate',
    'seat_count', 'transmission_type', 'fuel_type', 'mileage', 'status',
    'location_id', 'photos', 'metadata',
])]
/**
 * Relations registered dynamically by plugins via resolveRelationUsing():
 * - images(): HasMany (vehicle-media plugin)
 * - primaryImage(): HasOne (vehicle-media plugin)
 *
 * @method \Illuminate\Database\Eloquent\Relations\HasMany images()
 * @method \Illuminate\Database\Eloquent\Relations\HasOne  primaryImage()
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

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
