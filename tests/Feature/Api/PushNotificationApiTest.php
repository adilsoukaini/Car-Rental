<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Device-token registration API (Sanctum) for the mobile app's push
 * notifications. Runs against the in-memory SQLite DB like the other API
 * tests; the Expo HTTP call is faked — these tests verify the route contract
 * (register/unregister/test) and the DB effects, not the network.
 */
class PushNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('mobile')->plainTextToken;
    }

    public function test_register_stores_a_token_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/push/register', ['token' => 'ExponentPushToken[abc123]'])
            ->assertOk()
            ->assertJsonPath('message', 'Token registered.');

        $this->assertDatabaseHas('push_notification_tokens', [
            'user_id' => $user->id,
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'expo',
        ]);
    }

    public function test_register_accepts_an_explicit_platform(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/push/register', ['token' => 'ExponentPushToken[abc123]', 'platform' => 'fcm'])
            ->assertOk();

        $this->assertDatabaseHas('push_notification_tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'fcm',
        ]);
    }

    public function test_register_validates_platform_against_expo_and_fcm(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/push/register', ['token' => 'ExponentPushToken[abc123]', 'platform' => 'apns'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('platform');
    }

    public function test_register_requires_authentication(): void
    {
        $this->postJson('/api/push/register', ['token' => 'ExponentPushToken[abc123]'])
            ->assertStatus(401);
    }

    public function test_register_moves_an_existing_token_to_the_new_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->withToken($this->tokenFor($first))
            ->postJson('/api/push/register', ['token' => 'ExponentPushToken[shared]'])
            ->assertOk();

        // Same device, new account — the unique token row is re-parented. The
        // auth guard caches the first request's user in the shared test
        // container, so forget it before authenticating as the second user.
        Auth::forgetGuards();

        $this->withToken($this->tokenFor($second))
            ->postJson('/api/push/register', ['token' => 'ExponentPushToken[shared]'])
            ->assertOk();

        $this->assertDatabaseHas('push_notification_tokens', [
            'token' => 'ExponentPushToken[shared]',
            'user_id' => $second->id,
        ]);
        $this->assertDatabaseCount('push_notification_tokens', 1);
    }

    public function test_unregister_removes_a_named_token(): void
    {
        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/push/unregister', ['token' => 'ExponentPushToken[abc123]'])
            ->assertOk();

        $this->assertDatabaseCount('push_notification_tokens', 0);
    }

    public function test_unregister_without_a_token_removes_all_of_the_users_devices(): void
    {
        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[one]', 'platform' => 'expo']);
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[two]', 'platform' => 'expo']);

        // The mobile app's logout flow calls unregister() with no body.
        $this->withToken($this->tokenFor($user))
            ->postJson('/api/push/unregister')
            ->assertOk();

        $this->assertDatabaseCount('push_notification_tokens', 0);
    }

    public function test_unregister_does_not_remove_another_users_token(): void
    {
        $owner = User::factory()->create();
        $owner->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);
        $other = User::factory()->create();

        $this->withToken($this->tokenFor($other))
            ->postJson('/api/push/unregister', ['token' => 'ExponentPushToken[abc123]'])
            ->assertOk();

        $this->assertDatabaseHas('push_notification_tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'user_id' => $owner->id,
        ]);
    }

    public function test_unregister_requires_authentication(): void
    {
        $this->postJson('/api/push/unregister')->assertStatus(401);
    }

    public function test_test_endpoint_sends_a_push_to_the_authenticated_users_devices(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/push/test')
            ->assertOk()
            ->assertJsonPath('devices', 1);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $request->url() === config('services.expo.push_url')
                && $payload[0]['to'] === 'ExponentPushToken[abc123]'
                && $payload[0]['title'] === 'Test Push'
                && $payload[0]['data']['type'] === 'test';
        });
    }

    // -----------------------------------------------------------------------
    // Web storefront (VAPID) registration — the browser's PushSubscription
    // shape: endpoint + keys.{p256dh,auth} + expirationTime, platform 'web'.
    // -----------------------------------------------------------------------

    public function test_register_stores_a_web_subscription(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/push/register', [
                'platform' => 'web',
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'expirationTime' => null,
                'keys' => [
                    'p256dh' => 'BASE64URL_PUBLIC_KEY',
                    'auth' => 'BASE64URL_AUTH_SECRET',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Subscription registered.');

        $this->assertDatabaseHas('push_notification_tokens', [
            'user_id' => $user->id,
            'platform' => 'web',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'p256dh' => 'BASE64URL_PUBLIC_KEY',
            'auth' => 'BASE64URL_AUTH_SECRET',
        ]);
    }

    public function test_register_web_requires_the_push_keys(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/push/register', [
                'platform' => 'web',
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    public function test_register_web_requires_authentication(): void
    {
        $this->postJson('/api/push/register', [
            'platform' => 'web',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'x', 'auth' => 'y'],
        ])->assertStatus(401);
    }

    public function test_unregister_removes_a_web_subscription_by_endpoint(): void
    {
        $user = User::factory()->create();
        $user->pushNotificationTokens()->create([
            'platform' => 'web',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'p256dh' => 'x',
            'auth' => 'y',
        ]);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/push/unregister', [
                'platform' => 'web',
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            ])
            ->assertOk();

        $this->assertDatabaseCount('push_notification_tokens', 0);
    }

    // -----------------------------------------------------------------------
    // VAPID public key endpoint — public by design, so the browser can fetch it
    // before creating a subscription.
    // -----------------------------------------------------------------------

    public function test_vapid_public_key_endpoint_returns_the_configured_key(): void
    {
        config(['services.push.vapid_public_key' => 'TEST_PUBLIC_KEY']);

        // No auth token, no session — the endpoint is public.
        $this->getJson('/api/push/vapid-public-key')
            ->assertOk()
            ->assertJsonPath('public_key', 'TEST_PUBLIC_KEY');
    }

    public function test_vapid_public_key_endpoint_returns_503_when_not_configured(): void
    {
        config(['services.push.vapid_public_key' => null]);

        $this->getJson('/api/push/vapid-public-key')
            ->assertStatus(503)
            ->assertJsonPath('public_key', null);
    }
}
