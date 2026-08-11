<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'guest_email', 'booking_id',
        'type', 'title', 'body', 'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Scope: unread only. */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /** Get notifications for a given user or guest. */
    public static function forRecipient(?int $userId, ?string $guestEmail, int $limit = 20)
    {
        $query = static::query()->latest();

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($guestEmail) {
            $query->where('guest_email', $guestEmail);
        } else {
            return collect();
        }

        return $query->take($limit)->get();
    }

    /** Count unread for badge. */
    public static function unreadCount(?int $userId, ?string $guestEmail): int
    {
        if (! $userId && ! $guestEmail) {
            return 0;
        }
        $query = static::unread();
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('guest_email', $guestEmail);
        }

        return $query->count();
    }
}
