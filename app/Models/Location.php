<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address_line', 'city', 'country', 'latitude', 'longitude', 'is_active', 'metadata'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function pickupBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'pickup_location_id');
    }

    public function returnBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'return_location_id');
    }
}
