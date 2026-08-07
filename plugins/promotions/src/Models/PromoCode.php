<?php

declare(strict_types=1);

namespace Plugins\Promotions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code stored case-insensitively, matched via LOWER(code)
 * @property string $type 'percentage' | 'fixed'
 * @property string $value decimal, interpreted per-type (10 = 10%, 100 = 100.00 MAD)
 * @property string|null $min_booking_amount MAD minimum subtotal required, null = none
 * @property int|null $max_uses null = unlimited
 * @property int $uses_count incremented when a booking using this code is confirmed
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_booking_amount',
        'max_uses',
        'uses_count',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_booking_amount' => 'decimal:2',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
