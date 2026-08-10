<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\SendPushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the admin SendPushNotification page: the Admin-only access gate and
 * the broadcast action delegating to PushNotificationService::sendToAll() (the
 * same service the booking-event listeners use — one Expo implementation).
 */
class SendPushNotificationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_the_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->get('/admin/send-push-notification')->assertOk();
    }

    public function test_staff_cannot_access_the_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $this->get('/admin/send-push-notification')->assertForbidden();
    }

    public function test_send_broadcasts_to_every_registered_expo_device(): void
    {
        Http::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        User::factory()->create()
            ->pushNotificationTokens()->create(['token' => 'ExponentPushToken[one]', 'platform' => 'expo']);
        User::factory()->create()
            ->pushNotificationTokens()->create(['token' => 'ExponentPushToken[two]', 'platform' => 'expo']);

        Livewire::test(SendPushNotification::class)
            ->fillForm(['title' => 'Maintenance', 'body' => 'Fleet unavailable tomorrow'])
            ->call('send')
            ->assertHasNoFormErrors();

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $request->url() === config('services.expo.push_url')
                && $payload[0]['to'] === 'ExponentPushToken[one]'
                && $payload[1]['to'] === 'ExponentPushToken[two]'
                && $payload[0]['title'] === 'Maintenance'
                && $payload[0]['body'] === 'Fleet unavailable tomorrow'
                && $payload[0]['data']['type'] === 'announcement';
        });
    }
}
