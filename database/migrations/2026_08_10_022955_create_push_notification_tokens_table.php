<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Device push registrations for BOTH the mobile app and the web storefront.
     *
     *   - Mobile (platform 'expo' | 'fcm'): a device token. `token` holds the
     *     Expo/FCM registration token; it is unique across all users — a device
     *     can only ever receive pushes for the account that registered it.
     *   - Web (platform 'web'): a Web Push (VAPID) subscription. `endpoint`,
     *     `p256dh` and `auth` hold the three values the browser's
     *     PushSubscription exposes — the Web Push protocol needs all three to
     *     encrypt a payload for that device. `token` is null for web rows.
     *
     * Rows are always registered by an authenticated user (both clients require
     * auth) — a device never outlives its account, so a deleted user takes
     * their registrations with them (cascade delete).
     */
    public function up(): void
    {
        Schema::create('push_notification_tokens', function (Blueprint $table) {
            $table->id();
            // Tokens are always registered by an authenticated user (the mobile
            // app requires a Sanctum bearer token) — a device never outlives its
            // account, so a deleted user takes their tokens with them.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Mobile device token ('ExponentPushToken[xxxx]' or an FCM
            // registration token). Null for web (VAPID) subscriptions, which
            // use endpoint + p256dh + auth instead. The unique index tolerates
            // multiple NULLs (Postgres/SQLite), so web rows don't collide.
            $table->text('token')->nullable();
            $table->string('platform', 20)->default('expo'); // 'expo' | 'fcm' | 'web'
            $table->timestamps();

            // Web Push (VAPID) subscription — exactly what the browser's
            // PushManager.subscribe() resolves to. Null for mobile rows.
            $table->text('endpoint')->nullable();
            $table->text('p256dh')->nullable();
            $table->text('auth')->nullable();
            // The subscription's own expiry (rare for web push — the browser
            // reports expirationTime: null in the common case). Null means
            // "does not expire". Independent of this column, the WebPush
            // library reports 410 Gone for a dead subscription, which the
            // service uses to prune rows.
            $table->timestamp('expires_at')->nullable();

            $table->unique('token');
            $table->index(['user_id', 'platform']);

            // One row per web endpoint — a browser re-subscribing is an upsert,
            // never a duplicate. Partial index: only indexes non-null endpoints
            // (mobile rows leave it null). Postgres and SQLite both support it.
            $table->index('endpoint');
        });

        \Illuminate\Support\Facades\DB::statement(
            'create unique index push_notification_tokens_endpoint_unique '
            .'on push_notification_tokens (endpoint) where endpoint is not null'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notification_tokens');
    }
};
