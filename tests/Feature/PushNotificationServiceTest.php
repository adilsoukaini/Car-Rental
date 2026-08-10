<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PushNotificationService — the single implementation of the Expo HTTP call.
 * Verifies the request payload shape, the expo-only token filtering (FCM is
 * stubbed), the 100-message batch limit, and the best-effort resilience rule
 * (a network failure must never propagate).
 */
class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_to_user_builds_the_expo_request_shape(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);

        $count = app(PushNotificationService::class)->sendToUser($user, 'Hello', 'Body text', ['type' => 'x']);

        $this->assertSame(1, $count);
        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $request->url() === config('services.expo.push_url')
                && $request->hasHeader('Content-Type', 'application/json')
                && $payload[0]['to'] === 'ExponentPushToken[abc123]'
                && $payload[0]['sound'] === 'default'
                && $payload[0]['title'] === 'Hello'
                && $payload[0]['body'] === 'Body text'
                && $payload[0]['data'] === ['type' => 'x'];
        });
    }

    public function test_send_to_user_sends_only_expo_tokens_and_ignores_fcm(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[expo]', 'platform' => 'expo']);
        $user->pushNotificationTokens()->create(['token' => 'fcm-token', 'platform' => 'fcm']);

        $count = app(PushNotificationService::class)->sendToUser($user, 'Title', 'Body');

        $this->assertSame(1, $count);
        Http::assertSent(fn ($request) => str_contains($request->body(), 'ExponentPushToken[expo]')
            && ! str_contains($request->body(), 'fcm-token'));
    }

    public function test_send_to_user_with_no_tokens_makes_no_request(): void
    {
        Http::fake();

        $user = User::factory()->create();

        $count = app(PushNotificationService::class)->sendToUser($user, 'Title', 'Body');

        $this->assertSame(0, $count);
        Http::assertNothingSent();
    }

    public function test_send_to_all_sends_to_every_registered_device(): void
    {
        Http::fake();

        User::factory()->create()
            ->pushNotificationTokens()->create(['token' => 'ExponentPushToken[one]', 'platform' => 'expo']);
        User::factory()->create()
            ->pushNotificationTokens()->create(['token' => 'ExponentPushToken[two]', 'platform' => 'expo']);

        $count = app(PushNotificationService::class)->sendToAll('Announcement', 'Hello everyone');

        $this->assertSame(2, $count);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $payload[0]['to'] === 'ExponentPushToken[one]'
                && $payload[1]['to'] === 'ExponentPushToken[two]';
        });
    }

    public function test_send_batches_at_expo_100_message_limit(): void
    {
        Http::fake();

        $user = User::factory()->create();
        for ($i = 0; $i < 150; $i++) {
            $user->pushNotificationTokens()->create(['token' => "ExponentPushToken[t{$i}]", 'platform' => 'expo']);
        }

        $count = app(PushNotificationService::class)->sendToUser($user, 'Title', 'Body');

        $this->assertSame(150, $count);
        Http::assertSentCount(2);
    }

    public function test_send_does_not_throw_when_expo_is_unreachable(): void
    {
        Http::fake([
            '*' => function ($request) {
                throw new \Exception('Connection refused');
            },
        ]);

        $user = User::factory()->create();
        $user->pushNotificationTokens()->create(['token' => 'ExponentPushToken[abc123]', 'platform' => 'expo']);

        // Must not throw — the push is best-effort (resilience rule).
        $count = app(PushNotificationService::class)->sendToUser($user, 'Title', 'Body');

        $this->assertSame(1, $count);
    }
}
