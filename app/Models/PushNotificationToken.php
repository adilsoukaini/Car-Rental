<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A push registration for the mobile app or the web storefront.
 *
 * Mobile (`platform` 'expo' | 'fcm'): `token` holds the Expo/FCM device token
 * and is globally unique — one device can receive pushes for exactly one
 * account. Web (`platform` 'web'): `endpoint` + `p256dh` + `auth` hold the Web
 * Push (VAPID) subscription the browser created; `token` is null. Both channels
 * share this table so one query can target every device of a user (rule 8).
 *
 * @property int|null $user_id
 * @property string|null $token the raw Expo/FCM registration token (mobile)
 * @property string $platform 'expo', 'fcm' or 'web'
 * @property string|null $endpoint the web push subscription URL (web)
 * @property string|null $p256dh the subscription's base64url public key (web)
 * @property string|null $auth the subscription's base64url auth secret (web)
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
#[Fillable(['user_id', 'token', 'platform', 'endpoint', 'p256dh', 'auth', 'expires_at'])]
class PushNotificationToken extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * True for web-push rows — the ones the Web Push (VAPID) protocol sends to.
     */
    public function isWeb(): Attribute
    {
        return Attribute::get(fn (): bool => $this->platform === 'web');
    }
}
