<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushNotificationToken;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends push notifications to every device a user has registered — the mobile
 * app's Expo tokens AND the web storefront's Web Push (VAPID) subscriptions.
 * This is the single place the push HTTP calls are made; event listeners
 * (SendPushNotificationOnBookingEvents) and the admin SendPushNotification page
 * both delegate here.
 *
 * Delivery channels:
 *   - `platform = 'expo'` → Expo's Push API (the mobile app's
 *     `expo-notifications`), via an HTTP POST.
 *   - `platform = 'web'`  → the Web Push protocol (VAPID-signed, payload
 *     encrypted with the subscription's p256dh/auth keys) via the
 *     minishlink/web-push library. Requires VAPID keys in config/services.php;
 *     silently skips web rows when they're not configured.
 *   - `platform = 'fcm'`  → deliberately STUBBED for now (the mobile app is
 *     Expo-only). When a real FCM integration lands, add a sendToFcmTokens()
 *     branch (Firebase HTTP v1) — the schema already stores `platform` to make
 *     that switch a non-migration change.
 *
 * Resilience (CLAUDE.md "Resilience patterns" — external dependency fallback):
 * a push is best-effort. A failure to reach Expo or the push service must never
 * take down the request that triggered the notification (the booking-
 * confirmation request, the admin's send-click), so every send is wrapped in
 * try/catch and logged, never thrown. External HTTP timeouts are 5s to match
 * the project-wide standard (the project "Resilience patterns" rule 4).
 */
class PushNotificationService
{
    /** Expo caps each send request at 100 messages. */
    private const EXPO_BATCH_SIZE = 100;

    /**
     * Send a push to every device registered to the given user (web + mobile).
     *
     * @return int number of device registrations targeted (0 when the user has none)
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        $tokens = $user->pushNotificationTokens()
            ->whereIn('platform', ['expo', 'web'])
            ->get();

        return $this->send($title, $body, $data, $tokens);
    }

    /**
     * Send a push to every registered device (announcements from the admin
     * panel).
     *
     * @return int number of device registrations targeted
     */
    public function sendToAll(string $title, string $body, array $data = []): int
    {
        $tokens = PushNotificationToken::query()
            ->whereIn('platform', ['expo', 'web'])
            ->get();

        return $this->send($title, $body, $data, $tokens);
    }

    /**
     * @param  EloquentCollection<int, PushNotificationToken>  $tokens
     * @return int number of device registrations targeted
     */
    private function send(string $title, string $body, array $data, EloquentCollection $tokens): int
    {
        if ($tokens->isEmpty()) {
            return 0;
        }

        $this->sendExpo($title, $body, $data, $tokens->where('platform', '!=', 'web'));
        $this->sendWeb($title, $body, $data, $tokens->where('platform', 'web'));

        return $tokens->count();
    }

    /**
     * Expo Push API — the mobile app's channel. Sends in batches of 100 (Expo's
     * cap) with a 5s timeout, and never throws.
     *
     * @param  EloquentCollection<int, PushNotificationToken>  $tokens
     */
    private function sendExpo(string $title, string $body, array $data, EloquentCollection $tokens): void
    {
        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens
            ->filter(fn (PushNotificationToken $t) => filled($t->token))
            ->map(fn (PushNotificationToken $t): array => [
                'to' => $t->token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ])
            ->all();

        foreach (array_chunk($messages, self::EXPO_BATCH_SIZE) as $batch) {
            try {
                Http::asJson()
                    ->timeout(5)
                    ->post((string) config('services.expo.push_url'), $batch);
            } catch (\Throwable $e) {
                Log::warning('Expo push send failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch),
                ]);
            }
        }
    }

    /**
     * Web Push protocol (VAPID) — the storefront's channel. Payload is JSON so
     * the service worker can destructure { title, body, data } directly. A dead
     * subscription (HTTP 410 Gone — the browser revoked it, or it expired) is
     * pruned from the table so we stop sending to it.
     *
     * @param  EloquentCollection<int, PushNotificationToken>  $tokens
     */
    private function sendWeb(string $title, string $body, array $data, EloquentCollection $tokens): void
    {
        if ($tokens->isEmpty()) {
            return;
        }

        $publicKey = (string) config('services.push.vapid_public_key', '');
        $privateKey = (string) config('services.push.vapid_private_key', '');

        // VAPID keys not configured → the whole web channel is disabled. Log
        // once and return — this is the "external service unavailable" fallback
        // path, never a crash (the storefront still works without push).
        if ($publicKey === '' || $privateKey === '') {
            Log::warning('Web push skipped — VAPID keys not configured.');

            return;
        }

        try {
            $webPush = new WebPush(
                [
                    'VAPID' => [
                        'subject' => (string) config('services.push.vapid_subject', 'mailto:no-reply@'.(string) parse_url(config('app.url', 'localhost'), PHP_URL_HOST)),
                        'publicKey' => $publicKey,
                        'privateKey' => $privateKey,
                    ],
                ],
                [],
                new Client(['timeout' => 5]),
            );

            foreach ($tokens as $token) {
                if (blank($token->endpoint) || blank($token->p256dh) || blank($token->auth)) {
                    continue;
                }

                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $token->endpoint,
                        'publicKey' => $token->p256dh,
                        'authToken' => $token->auth,
                        'contentEncoding' => 'aes128gcm',
                    ]),
                    json_encode([
                        'title' => $title,
                        'body' => $body,
                        'data' => $data,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                );
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushNotificationToken::query()
                        ->where('endpoint', $report->getEndpoint())
                        ->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Web push send failed', [
                'error' => $e->getMessage(),
                'targets' => $tokens->count(),
            ]);
        }
    }
}
