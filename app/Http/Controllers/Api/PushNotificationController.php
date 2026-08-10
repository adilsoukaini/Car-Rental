<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushNotificationToken;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Push-registration endpoints for BOTH clients.
 *
 * Mobile app: POST /api/push/register after login (and on app start) with the
 * Expo push token it received from `expo-notifications`, and POST
 * /api/push/unregister on logout. Both behind auth:sanctum (Bearer token).
 *
 * Web storefront: POST /api/push/register with the browser's Web Push (VAPID)
 * subscription ({ endpoint, keys: { p256dh, auth } }, platform 'web') after the
 * user grants notification permission. The same routes carry the `web` session
 * middleware (added in routes/api.php) so a browser session cookie
 * authenticates them — the `api` middleware group alone doesn't start a
 * session. CSRF is excluded for /api/* in bootstrap/app.php.
 *
 * The unique-registration contract: a mobile token is globally unique (one
 * device → one account); a web subscription is unique per endpoint. Both are
 * upserts — re-registering the same device/subscription updates the row rather
 * than duplicating it.
 */
class PushNotificationController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $push,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $platform = $request->input('platform', 'expo');

        if ($platform === 'web') {
            return $this->registerWeb($request);
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['sometimes', 'string', 'in:expo,fcm'],
        ]);

        PushNotificationToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'expo',
            ],
        );

        return response()->json(['message' => 'Token registered.']);
    }

    public function unregister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['sometimes', 'string', 'max:512'],
            'endpoint' => ['sometimes', 'string', 'max:2048'],
        ]);

        // The mobile app's api.push.unregister() sends no body — it relies on
        // the Bearer token to identify the user, so no token/endpoint here
        // means "drop every registration this user has" (the logout case). A
        // specific token (mobile) or endpoint (web) is still honored for
        // callers that know which device to remove.
        $query = PushNotificationToken::query()->where('user_id', $request->user()->id);

        if (! empty($validated['endpoint'])) {
            $query->where('endpoint', $validated['endpoint']);
        } elseif (! empty($validated['token'])) {
            $query->where('token', $validated['token']);
        }

        $query->delete();

        return response()->json(['message' => 'Token unregistered.']);
    }

    /**
     * Test route — sends a push to the authenticated user's own devices.
     * Used to verify the whole chain (register → Expo/VAPID send) from curl.
     */
    public function test(Request $request): JsonResponse
    {
        $count = $this->push->sendToUser(
            $request->user(),
            'Test Push',
            'This is a test notification from Car Rental.',
            ['type' => 'test'],
        );

        return response()->json([
            'message' => 'Test push sent.',
            'devices' => $count,
        ]);
    }

    /**
     * The VAPID public key the browser needs to create a push subscription.
     * Public by design — the key is meant to be shared with every client; the
     * PRIVATE key never leaves the server. Returns 503 when VAPID isn't
     * configured so the frontend degrades silently (no subscription is created).
     */
    public function vapidPublicKey(): JsonResponse
    {
        $key = (string) config('services.push.vapid_public_key', '');

        if ($key === '') {
            return response()->json(['public_key' => null], 503);
        }

        return response()->json(['public_key' => $key]);
    }

    /**
     * Register a web push (VAPID) subscription. The browser sends the
     * PushSubscription shape: endpoint + keys.{p256dh,auth} + expirationTime
     * (DOMHighResTimeStamp in ms, usually null).
     */
    private function registerWeb(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:web'],
            'endpoint' => ['required', 'string', 'max:2048'],
            'expirationTime' => ['nullable', 'integer'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:1024'],
            'keys.auth' => ['required', 'string', 'max:1024'],
        ]);

        $expiresAt = isset($validated['expirationTime'])
            ? Carbon::createFromTimestampMs((int) $validated['expirationTime'])
            : null;

        // endpoint is unique (partial index) — re-subscribing the same browser
        // subscription updates the row instead of duplicating it.
        PushNotificationToken::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $request->user()->id,
                'platform' => 'web',
                'p256dh' => $validated['keys']['p256dh'],
                'auth' => $validated['keys']['auth'],
                'expires_at' => $expiresAt,
            ],
        );

        return response()->json(['message' => 'Subscription registered.']);
    }
}
