<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property Carbon $pickup_at
 * @property Carbon $return_at
 * @property string $status
 * @property string $booking_number random public booking reference — auto-generated on create, never user-supplied (mass-assignment guarded)
 * @property Carbon|null $hold_expires_at
 * @property-read User|null $user null for a guest booking — see guest_name/guest_email/guest_phone
 */
#[Fillable([
    'vehicle_id', 'user_id', 'guest_name', 'guest_email', 'guest_phone',
    'pickup_location_id', 'return_location_id', 'pickup_at', 'return_at',
    'status', 'hold_expires_at', 'total_price', 'security_deposit_amount', 'metadata',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = strtoupper(Str::random(10));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime',
            'return_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'total_price' => 'decimal:2',
            'security_deposit_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function returnLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'return_location_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }
}
